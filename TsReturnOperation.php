<?php

namespace NW\WebService\References\Operations\Notification;

use Exception;

class TsReturnOperation extends ReferencesOperation
{

    /**
     * Type New
     */
    public const TYPE_NEW    = 1;

    /**
     * Type Change
     */
    public const TYPE_CHANGE = 2;

    /**
     * Notification type
     *
     * @var integer
     */
    public int $notificationType;

    /**
     * Reseller id
     *
     * @var integer
     */
    public int $resellerId;

    /**
     * Client
     *
     * @var Contractor
     */
    public Contractor $client;

    /**
     * Creator
     *
     * @var Contractor
     */
    public Contractor $creator;

    /**
     * Expert
     *
     * @var Contractor
     */
    public Contractor $expert;

    /**
     * Do operation
     *
     * @throws Exception
     *
     * @return array
     */
    public function doOperation(): array
    {
        $data                   = $this->getRequest('data');
        $result                 = $this->getDefaultResult();
        $this->resellerId       = $data['resellerId'];
        $this->notificationType = (int) $data['notificationType'];

        if (true === empty($this->resellerId)) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        $this->checkReseller();

        if (true === empty($this->notificationType)) {
            throw new Exception('Empty notificationType', 400);
        }

        $this->client  = $this->getClient($data['clientId']);
        $this->creator = $this->getEmployee($data['creatorId']);
        $this->expert  = $this->getEmployee($data['expertId']);
        $differences   = $this->getDifferences($data);
        $templateData  = $this->prepareTemplateData($data, $differences);

        $this->validateTemplateData($templateData);

        return $this->sendNotifications($data, $templateData, $result);
    }

    /**
     * Get default result
     *
     * @return array
     */
    private function getDefaultResult(): array
    {
        return [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
        ];
    }

    /**
     * Get client
     *
     * @param int $clientId Client id
     *
     * @return Contractor
     * @throws Exception
     */
    private function getClient(int $clientId): Contractor
    {
        $client = Contractor::getById($clientId);
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $this->resellerId) {
            throw new Exception('Client not found!', 400);
        }
        return $client;
    }

    /**
     * Get employee
     *
     * @param int $employeeId Employee id
     *
     * @return Contractor
     * @throws Exception
     */
    private function getEmployee(int $employeeId): Contractor
    {
        $employee = Employee::getById($employeeId);

        if ($employee === null) {
            throw new Exception('Employee not found!', 400);
        }

        return $employee;
    }


    /**
     * Get differences
     *
     * @param array $data Data
     *
     * @return String
     */
    private function getDifferences(array $data): string
    {
        if ($this->notificationType === self::TYPE_NEW) {
            return __('NewPositionAdded', null, $data['resellerId']);
        }

        if ($this->notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            return __('PositionStatusHasChanged',
                [
                'FROM' => Status::getName((int)$data['differences']['from']),
                'TO' => Status::getName((int)$data['differences']['to']),
                ],
                $data['resellerId']
            );
        }

        return '';
    }

    /**
     * Prepare template data
     *
     * @param array  $data        Data
     * @param string $differences Differences
     *
     * @return array
     */
    private function prepareTemplateData(array $data, string $differences): array
    {
        return [
            'COMPLAINT_ID'       => (int) $data['complaintId'],
            'COMPLAINT_NUMBER'   => (string) $data['complaintNumber'],
            'CREATOR_ID'         => (int) $data['creatorId'],
            'CREATOR_NAME'       => $this->creator->getFullName(),
            'EXPERT_ID'          => (int) $data['expertId'],
            'EXPERT_NAME'        => $this->expert->getFullName(),
            'CLIENT_ID'          => (int) $data['clientId'],
            'CLIENT_NAME'        => $this->client->getFullName(),
            'CONSUMPTION_ID'     => (int) $data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string) $data['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string) $data['agreementNumber'],
            'DATE'               => (string) $data['date'],
            'DIFFERENCES'        => $differences,
        ];
    }

    /**
     * Validate template data
     *
     * @param array $templateData Template data
     *
     * @return void
     * @throws Exception
     */
    private function validateTemplateData(array $templateData): void
    {
        foreach ($templateData as $key => $tempData) {
            if (true === empty($tempData)) {
                throw new Exception("Template Data ({$key}) is empty!", 500);
            }
        }
    }

    /**
     * Send notifications
     *
     * @param array $data         Data
     * @param array $templateData Template data
     * @param array $result       Result
     *
     * @return array
     */
    private function sendNotifications(array $data, array $templateData, array $result): array
    {
        $emailFrom = getResellerEmailFrom($this->resellerId);
        $emails = getEmailsByPermit($this->resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    [
                        'emailFrom' => $emailFrom,
                        'emailTo' => $email,
                        'subject' => __('complaintEmployeeEmailSubject', $templateData, $this->resellerId),
                        'message' => __('complaintEmployeeEmailBody', $templateData, $this->resellerId),
                    ],
                ], $this->resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;
            }
        }

        if ($this->notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            $result = $this->sendClientNotifications($data, $templateData, $result);
        }

        return $result;
    }


    /**
     * Send client notifications
     *
     * @param array $data         Data
     * @param array $templateData Template data
     * @param array $result       Result
     *
     * @return array
     */
    private function sendClientNotifications(array $data, array $templateData, array $result): array
    {
        $emailFrom = getResellerEmailFrom($this->resellerId);
        if (false === empty($emailFrom) && false === empty($this->client->email)) {
            MessagesClient::sendMessage(
                [
                    [
                        'emailFrom' => $emailFrom,
                        'emailTo' => $this->client->email,
                        'subject' => __('complaintClientEmailSubject', $templateData, $this->resellerId),
                        'message' => __('complaintClientEmailBody', $templateData, $this->resellerId),
                    ],
                ],
                $this->resellerId,
                $this->client->id,
                NotificationEvents::CHANGE_RETURN_STATUS,
                (int) $data['differences']['to']
            );

            $result['notificationClientByEmail'] = true;
        }

        if (false === empty($this->client->mobile)) {
            $error = '';
            $res   = NotificationManager::send(
                $this->resellerId,
                $this->client->id,
                NotificationEvents::CHANGE_RETURN_STATUS,
                (int) $data['differences']['to'],
                $templateData,
                $error
            );

            if ($res) {
                $result['notificationClientBySms']['isSent'] = true;
            }

            if (false === empty($error)) {
                $result['notificationClientBySms']['message'] = $error;
            }
        }

        return $result;
    }

    /**
     * Check reseller for existing
     *
     * @return void
     * @throws Exception
     */
    private function checkReseller(): void
    {
        if (null === Seller::getById($this->resellerId)) {
            throw new Exception('Seller not found!', 404);
        }
    }
}
