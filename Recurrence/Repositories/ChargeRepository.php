<?php

namespace PlugHacker\PlugCore\Recurrence\Repositories;

use Exception;
use PlugHacker\PlugCore\Kernel\Abstractions\AbstractDatabaseDecorator;
use PlugHacker\PlugCore\Kernel\Abstractions\AbstractEntity;
use PlugHacker\PlugCore\Kernel\Abstractions\AbstractRepository;
use PlugHacker\PlugCore\Kernel\Aggregates\Charge;
use PlugHacker\PlugCore\Kernel\Factories\ConfigurationFactory;
use PlugHacker\PlugCore\Recurrence\Factories\ChargeFactory;
use PlugHacker\PlugCore\Kernel\ValueObjects\AbstractValidString;
use PlugHacker\PlugCore\Kernel\ValueObjects\Id\OrderId;
use PlugHacker\PlugCore\Kernel\Repositories\TransactionRepository;
use PlugHacker\PlugCore\Kernel\Exceptions\InvalidParamException;

final class ChargeRepository extends AbstractRepository
{
    public function findByOrderId(OrderId $orderId)
    {
        $chargeTable = $this->db->getTable(AbstractDatabaseDecorator::TABLE_RECURRENCE_CHARGE);
        $transactionTable = $this->db->getTable(AbstractDatabaseDecorator::TABLE_TRANSACTION);

        $id = $orderId->getValue();

        $query = "
            SELECT
                c.*,
                GROUP_CONCAT(c.id) as id,
                GROUP_CONCAT(t.id) as tran_id,
                GROUP_CONCAT(t.plug_id) as tran_plug_id,
                GROUP_CONCAT(t.charge_id) as tran_charge_id,
                GROUP_CONCAT(t.amount) as tran_amount,
                GROUP_CONCAT(t.paid_amount) as tran_paid_amount,
                GROUP_CONCAT(t.acquirer_name) as tran_acquirer_name,
                GROUP_CONCAT(t.acquirer_message) as tran_acquirer_message,
                GROUP_CONCAT(t.acquirer_nsu) as tran_acquirer_nsu,
                GROUP_CONCAT(t.acquirer_tid) as tran_acquirer_tid,
                GROUP_CONCAT(t.acquirer_auth_code) as tran_acquirer_auth_code,
                GROUP_CONCAT(t.type) as tran_type,
                GROUP_CONCAT(t.status) as tran_status,
                GROUP_CONCAT(t.created_at) as tran_created_at,
                GROUP_CONCAT(t.boleto_url) as tran_boleto_url,
                GROUP_CONCAT(t.card_data SEPARATOR '---') as tran_card_data
            FROM
                $chargeTable as c
                LEFT JOIN $transactionTable as t
                  ON c.plug_id = t.charge_id
            WHERE c.order_id = '$id'
            GROUP BY c.id;
        ";

        $result = $this->db->fetch($query);

        if ($result->num_rows === 0) {
            return [];
        }

        $factory = new ChargeFactory();

        $charges = [];
        foreach ($result->rows as $row) {
            $charges[] = $factory->createFromDbData($row);
        }

        return $charges;
    }

    /**
     *
     * @param Charge|AbstractEntity $object
     * @throws Exception
     */
    protected function create(AbstractEntity &$object)
    {
        $chargeTable = $this->db->getTable(AbstractDatabaseDecorator::TABLE_RECURRENCE_CHARGE);

        $query = "
          INSERT INTO
            $chargeTable
            (
                plug_id,
                invoice_id,
                subscription_id,
                code,
                amount,
                paid_amount,
                canceled_amount,
                refunded_amount,
                status,
                metadata,
                payment_method,
                boleto_link,
                cycle_start,
                cycle_end
            )
          VALUES
        ";

        $metadata = \json_encode($object->getMetadata());

        $query .= "
            (
                '{$object->getPlugId()->getValue()}',
                '{$object->getInvoiceId()}',
                '{$object->getSubscriptionId()}',
                '{$object->getCode()}',
                {$object->getAmount()},
                {$object->getPaidAmount()},
                {$object->getCanceledAmount()},
                {$object->getRefundedAmount()},
                '{$object->getStatus()->getStatus()}',
                '{$metadata}',
                '{$object->getPaymentMethod()->getPaymentMethod()}',
                '{$object->getBoletoUrl()}',
               '{$object->getCycleStart()->format('Y-m-d H:i:s')}',
               '{$object->getCycleEnd()->format('Y-m-d H:i:s')}'
            );
        ";

        $this->db->query($query);

        $transactionRepository = new TransactionRepository();
        foreach ($object->getTransactions() as $transaction) {
            $transactionRepository->save($transaction);
            $object->updateTransaction($transaction, true);
        }
    }

    protected function update(AbstractEntity &$object)
    {
        $charge = json_decode(json_encode($object));
        $chargeTable = $this->db->getTable(AbstractDatabaseDecorator::TABLE_RECURRENCE_CHARGE);

        $metadata = null;
        if (!empty($charge->metadata)) {
            $metadata = json_encode($charge->metadata);
        }

        $query = "
            UPDATE $chargeTable SET
              amount = {$charge->amount},
              paid_amount = {$charge->paidAmount},
              refunded_amount = {$charge->refundedAmount},
              canceled_amount = {$charge->canceledAmount},
              status = '{$charge->status}',
              metadata = '{$metadata}'
            WHERE id = {$charge->id}
        ";

        $this->db->query($query);
    }

    public function delete(AbstractEntity $object)
    {
        // TODO: Implement delete() method.
    }

    public function find($objectId)
    {
        /** @todo Implement find() method. **/
    }

    public function findByInvoiceId($invoiceId)
    {
        $table = $this->db->getTable(
            AbstractDatabaseDecorator::TABLE_RECURRENCE_CHARGE
        );

        $query = "
            SELECT
                id,
                plug_id,
                subscription_id,
               invoice_id,
               `code`,
               amount,
               paid_amount,
               canceled_amount,
               refunded_amount,
               `status`,
               metadata,
               payment_method,
               boleto_link,
               cycle_start,
               cycle_end

            FROM `$table` WHERE invoice_id = '{$invoiceId}';
            ";

        $result = $this->db->fetch($query);

        $factory = new ChargeFactory();

        if (empty($result->row)) {
            return null;
        }

        $charge =  $factory->createFromDbData($result->row);

        return $charge;
    }

    public function listEntities($limit, $listDisabled)
    {
        // TODO: Implement listEntities() method.
    }


    public function findByPlugId(AbstractValidString $plugId)
    {
        $chargeTable = $this->db->getTable(AbstractDatabaseDecorator::TABLE_RECURRENCE_CHARGE);
        $transactionTable = $this->db->getTable(AbstractDatabaseDecorator::TABLE_TRANSACTION);

        $id = $plugId->getValue();

        $query = "
            SELECT
                c.*,
                GROUP_CONCAT(t.id) as tran_id,
                GROUP_CONCAT(t.plug_id) as tran_plug_id,
                GROUP_CONCAT(t.charge_id) as tran_charge_id,
                GROUP_CONCAT(t.amount) as tran_amount,
                GROUP_CONCAT(t.paid_amount) as tran_paid_amount,
                GROUP_CONCAT(t.acquirer_name) as tran_acquirer_name,
                GROUP_CONCAT(t.acquirer_message) as tran_acquirer_message,
                GROUP_CONCAT(t.acquirer_nsu) as tran_acquirer_nsu,
                GROUP_CONCAT(t.acquirer_tid) as tran_acquirer_tid,
                GROUP_CONCAT(t.acquirer_auth_code) as tran_acquirer_auth_code,
                GROUP_CONCAT(t.type) as tran_type,
                GROUP_CONCAT(t.status) as tran_status,
                GROUP_CONCAT(t.created_at) as tran_created_at,
                GROUP_CONCAT(t.boleto_url) as tran_boleto_url,
                GROUP_CONCAT(t.card_data SEPARATOR '---') as tran_card_data
            FROM
                $chargeTable as c
                LEFT JOIN $transactionTable as t
                  ON c.plug_id = t.charge_id
            WHERE c.plug_id = '$id'
            GROUP BY c.id;
        ";

        $result = $this->db->fetch($query);

        if ($result->num_rows === 0) {
            return null;
        }

        $factory = new ChargeFactory();
        return $factory->createFromDbData($result->row);
    }

    /**
     * @param $codeOrder
     * @return Charge[]|array
     * @throws InvalidParamException
     */
    public function findByCode($codeOrder)
    {
        $chargeTable = $this->db->getTable(
            AbstractDatabaseDecorator::TABLE_RECURRENCE_CHARGE
        );

        $transactionTable = $this->db->getTable(
            AbstractDatabaseDecorator::TABLE_TRANSACTION
        );

        $query = "
            SELECT
                recurrence_charge.*,
                GROUP_CONCAT(recurrence_charge.id) as id,
                GROUP_CONCAT(t.id) as tran_id,
                GROUP_CONCAT(t.plug_id) as tran_plug_id,
                GROUP_CONCAT(t.charge_id) as tran_charge_id,
                GROUP_CONCAT(t.amount) as tran_amount,
                GROUP_CONCAT(t.paid_amount) as tran_paid_amount,
                GROUP_CONCAT(t.acquirer_name) as tran_acquirer_name,
                GROUP_CONCAT(t.acquirer_message) as tran_acquirer_message,
                GROUP_CONCAT(t.acquirer_nsu) as tran_acquirer_nsu,
                GROUP_CONCAT(t.acquirer_tid) as tran_acquirer_tid,
                GROUP_CONCAT(t.acquirer_auth_code) as tran_acquirer_auth_code,
                GROUP_CONCAT(t.type) as tran_type,
                GROUP_CONCAT(t.status) as tran_status,
                GROUP_CONCAT(t.created_at) as tran_created_at,
                GROUP_CONCAT(t.boleto_url) as tran_boleto_url,
                GROUP_CONCAT(t.card_data SEPARATOR '---') as tran_card_data
            FROM
                {$chargeTable} as recurrence_charge
                LEFT JOIN {$transactionTable} as t
                       ON recurrence_charge.plug_id = t.charge_id
            WHERE recurrence_charge.code = '{$codeOrder}'
            GROUP BY recurrence_charge.id;
        ";

        $result = $this->db->fetch($query);

        if ($result->num_rows === 0) {
            return [];
        }

        $factory = new ChargeFactory();

        $charges = [];
        foreach ($result->rows as $row) {
            $charges[] = $factory->createFromDbData($row);
        }

        return $charges;
    }

    public function findBySubscriptionId(AbstractValidString $subscriptionId)
    {
        $chargeTable = $this->db->getTable(AbstractDatabaseDecorator::TABLE_RECURRENCE_CHARGE);

        $id = $subscriptionId->getValue();

        $query = "
            SELECT
                *
            FROM
                $chargeTable as c
            WHERE subscription_id = '$id'
        ";

        $result = $this->db->fetch($query);

        if ($result->num_rows === 0) {
            return [];
        }

        $charges = [];

        foreach ($result->rows as $row) {
            $factory = new ChargeFactory();
            $charges[] = $factory->createFromDbData($row);
        }

        return $charges;
    }
}
