<?php
/**
 * File: src/Interfaces/Controller.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Interfaces
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */



namespace Zero\Interfaces;

/**
 * Interface Controller
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
interface Controller
{
    /**
     * Handle the incoming request action.
     *
     * @param mixed $param Can be regex matches array, Page model record, or context data.
     */
    public function handle($param);
}
