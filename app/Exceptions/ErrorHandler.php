<?php

namespace App\Exceptions;

use Exception;

class ErrorHandler extends Exception
{
    public $errorType;
    public function __construct($message, $error = 'error', $code = 0)
    {
        $this->errorType = $error;
        parent::__construct($message, $code);
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function render($request)
    {
        $error = [
            'error' => $this->errorType,
            'code' => $this->code,
            'message' => $this->getMessage(),
        ];

        return $error;
    }
}
