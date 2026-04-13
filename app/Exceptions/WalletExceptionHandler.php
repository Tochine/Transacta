<?php

namespace App\Exceptions;

use Exception;

class WalletExceptionHandler extends Exception
{
    public function __construct($message, $error = 'error', $code = 0)
    {
        parent::__construct($message, $error, $code);
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
            'error' => $error,
            'code' => $this->code,
            'message' => $this->getMessage(),
        ];

        return $error;
    }
}
