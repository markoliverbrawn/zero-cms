<?php
/**
 * File: src/Modules/Shop/Models/Order.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */



namespace Zero\Modules\Shop\Models;

use Zero\Interfaces\Model;
use Zero\Models\Traits\CascadesDeletes;
use Zero\Models\Traits\IsModel;
use Zero\Modules\Shop\Models\OrderItem;

/**
 * Class Order
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Order implements Model
{
    use IsModel, CascadesDeletes {
        CascadesDeletes::delete insteadof IsModel;
        CascadesDeletes::forceDelete insteadof IsModel;
        IsModel::delete as traitDelete;
        IsModel::forceDelete as traitForceDelete;
    }

    protected static $tableName = 'shop_orders';
    protected static $modelType = 'order';
    protected static $fillable = ['site_id', 'customer_name', 'customer_email', 'total_price', 'status', 'shipping_address'];
    protected static array $cascadeDeletes = [
        OrderItem::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class => 'order_id'
    ];

    public $id;
    public $site_id;
    public $customer_name;
    public $customer_email;
    public $total_price;
    public $status;
    public $shipping_address;
    public $created_at;
    public $updated_at;

    /**
     * Retrieves the config attribute value.
     *
     * @return mixed Response output.
     */
    public static function getConfig(): array
    {
        return [
            'id' => ['type' => 'text', 'label' => 'Order ID (UUIDv7)', 'editable' => false, 'listDisplay' => true],
            'customer_name' => ['type' => 'text', 'label' => 'Customer Name', 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true],
            'customer_email' => ['type' => 'email', 'label' => 'Customer Email', 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true],
            'total_price' => ['type' => 'number', 'label' => 'Total Price', 'editable' => true, 'required' => true, 'listDisplay' => true],
            'status' => [
                'type' => 'select',
                'label' => 'Order Status',
                'options' => [
                    'pending' => 'Pending Payment',
                    'paid' => 'Payment Settled',
                    'shipping' => 'Dispatched',
                    'cancelled' => 'Cancelled'
                ],
                'editable' => true,
                'listDisplay' => true,
                'required' => true
            ],
            'shipping_address' => ['type' => 'textarea', 'label' => 'Shipping Address', 'editable' => true, 'required' => true, 'listDisplay' => false],
            'created_at' => ['type' => 'datetime', 'label' => 'Created At', 'editable' => false, 'listDisplay' => true]
        ];
    }

    /**
     * Get order items.
     */
    public function getItems(): array
    {
        return OrderItem::where('order_id', $this->id);
    }
}
