<?php

namespace Zero\Interfaces;

interface Controller
{
    /**
     * Handle the incoming request action.
     *
     * @param mixed $param Can be regex matches array, Page model record, or context data.
     */
    public function handle($param);
}
