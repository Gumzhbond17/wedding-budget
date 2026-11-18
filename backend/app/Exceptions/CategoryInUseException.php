<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Thrown when a category still referenced by revenue or expense records is deleted.
 * Laravel calls render() automatically, so controllers need no try/catch.
 */
final class CategoryInUseException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            'This category is still used by revenue or expense records, including deleted ones. Move them to another category first.',
            previous: $previous,
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'category_in_use',
        ], Response::HTTP_CONFLICT);
    }
}
