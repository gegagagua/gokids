<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Laravel\Sanctum\HasApiTokens;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if (str_starts_with($request->path(), 'api/')) {
            // ვალიდაციის შეცდომისთვის
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors(),
                ], 422);
            }
            // სხვა ყველა შეცდომისთვის
            return response()->json([
                'message' => $exception->getMessage(),
            ], method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500);
        }
        
        // Clean exception message to prevent null byte issues
        $message = $exception->getMessage();
        if ($message) {
            $message = str_replace("\0", '', $message); // Remove null bytes
            $message = trim($message); // Remove whitespace
        }
        
        // Create a new exception with cleaned message if needed
        if ($message !== $exception->getMessage()) {
            $exception = new \Exception($message, $exception->getCode(), $exception);
        }
        
        return parent::render($request, $exception);
    }
} 