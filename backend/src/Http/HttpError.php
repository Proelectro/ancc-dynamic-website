<?php

declare(strict_types=1);

namespace Ancc\Http;

use RuntimeException;

/**
 * Throw to return an error response: {"detail": ...}.
 * $detail is a message string, or a list of {loc, msg} items for validation errors (422).
 */
final class HttpError extends RuntimeException
{
    /** @param string|list<array{loc: list<string>, msg: string}> $detail */
    public function __construct(public readonly int $status, public readonly string|array $detail)
    {
        parent::__construct(is_string($detail) ? $detail : 'Validation error');
    }

    /** @param array<string, string> $errors field => message */
    public static function validation(array $errors): self
    {
        $items = [];
        foreach ($errors as $field => $msg) {
            $items[] = ['loc' => ['body', $field], 'msg' => $msg];
        }
        return new self(422, $items);
    }
}
