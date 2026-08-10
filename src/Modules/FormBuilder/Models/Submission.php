<?php
/**
 * File: src/Modules/FormBuilder/Models/Submission.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\FormBuilder\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\FormBuilder\Models;

use Zero\Interfaces\Model;
use Zero\Models\Traits\IsModel;

/**
 * Class Submission
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Submission implements Model
{
    use IsModel;

    protected static string $tableName = 'form_submissions';
    protected static array $fillable = ['name', 'email', 'phone', 'message'];

    public $site_id;
    public $name;
    public $email;
    public $phone;
    public $message;
    public $form_title = 'Contact Form';
    public $source_page = 'Contact Page';
    public $formatted_fields = [];
    public $created_at;

    /**
     * __construct processing implementation helper.
     *
     * @param array $data Argument descriptor.
     * @return mixed Response output.
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Dynamically decode and parse the rich JSON message payload
        if (!empty($this->message)) {
            $decoded = json_decode($this->message, true);
            if (is_array($decoded)) {
                $this->form_title = $decoded['_meta_form_title'] ?? 'Contact Form';
                $this->source_page = $decoded['_meta_source_page'] ?? 'Contact Page';
                
                // Clear metadata keys to leave only actual form submission inputs
                $fields = [];
                foreach ($decoded as $label => $val) {
                    if (strpos($label, '_meta_') !== 0) {
                        $fields[$label] = $val;
                    }
                }
                $this->formatted_fields = $fields;
            }
        }
    }

    /**
     * Retrieves the config attribute value.
     *
     * @return mixed Response output.
     */
    public static function getConfig(): array
    {
        return [
            'id' => ['type' => 'text', 'label' => 'ID', 'editable' => false, 'listDisplay' => false],
            'form_title' => ['type' => 'text', 'label' => 'Form Block', 'editable' => false, 'listDisplay' => true],
            'source_page' => ['type' => 'text', 'label' => 'Source Page', 'editable' => false, 'listDisplay' => true],
            'name' => ['type' => 'text', 'label' => 'Sender Name', 'editable' => false, 'listDisplay' => true, 'searchable' => true],
            'email' => ['type' => 'text', 'label' => 'Sender Email', 'editable' => false, 'listDisplay' => true, 'searchable' => true],
            'phone' => ['type' => 'text', 'label' => 'Sender Phone', 'editable' => false, 'listDisplay' => true, 'searchable' => true],
            'message' => ['type' => 'textarea', 'label' => 'Submission Data (JSON)', 'editable' => false, 'listDisplay' => false],
            'created_at' => ['type' => 'datetime', 'label' => 'Received At', 'editable' => false, 'listDisplay' => true]
        ];
    }

    /**
     * Retrieves the edit label attribute value.
     *
     * @return string Response output.
     */
    public static function getEditLabel(): string
    {
        return 'View';
    }
}
