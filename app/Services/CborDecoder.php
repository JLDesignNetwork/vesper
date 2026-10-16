<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Minimal CBOR (RFC 8949) decoder for WebAuthn attestation objects and COSE keys.
 *
 * Supports the subset WebAuthn uses: unsigned and negative integers, byte strings,
 * text strings, arrays, maps, and the simple values false, true and null. Indefinite
 * lengths, tags and floats are rejected.
 */
class CborDecoder
{
    private string $data;

    private int $offset = 0;

    /**
     * Decode the first CBOR item in $data. $consumed receives how many bytes it used,
     * so callers can find data that follows it (the attested credential's COSE key is
     * followed by optional extensions).
     */
    public function decode(string $data, ?int &$consumed = null): mixed
    {
        $this->data = $data;
        $this->offset = 0;

        $value = $this->readItem();
        $consumed = $this->offset;

        return $value;
    }

    private function readItem(): mixed
    {
        $initial = $this->readByte();
        $majorType = $initial >> 5;
        $info = $initial & 0x1F;

        if ($majorType === 7) {
            return match ($info) {
                20 => false,
                21 => true,
                22 => null,
                default => throw new InvalidArgumentException('Unsupported CBOR simple value.'),
            };
        }

        $argument = $this->readArgument($info);

        return match ($majorType) {
            0 => $argument,
            1 => -1 - $argument,
            2 => $this->readBytes($argument),
            3 => $this->readBytes($argument),
            4 => $this->readArray($argument),
            5 => $this->readMap($argument),
            default => throw new InvalidArgumentException('Unsupported CBOR major type.'),
        };
    }

    private function readArgument(int $info): int
    {
        return match (true) {
            $info < 24 => $info,
            $info === 24 => $this->readByte(),
            $info === 25 => unpack('n', $this->readBytes(2))[1],
            $info === 26 => unpack('N', $this->readBytes(4))[1],
            $info === 27 => unpack('J', $this->readBytes(8))[1],
            default => throw new InvalidArgumentException('Indefinite or reserved CBOR lengths are not supported.'),
        };
    }

    /**
     * @return list<mixed>
     */
    private function readArray(int $length): array
    {
        $items = [];
        for ($i = 0; $i < $length; $i++) {
            $items[] = $this->readItem();
        }

        return $items;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function readMap(int $length): array
    {
        $map = [];
        for ($i = 0; $i < $length; $i++) {
            $key = $this->readItem();
            if (! is_int($key) && ! is_string($key)) {
                throw new InvalidArgumentException('Unsupported CBOR map key.');
            }
            $map[$key] = $this->readItem();
        }

        return $map;
    }

    private function readByte(): int
    {
        return ord($this->readBytes(1));
    }

    private function readBytes(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->data)) {
            throw new InvalidArgumentException('Truncated CBOR data.');
        }

        $bytes = substr($this->data, $this->offset, $length);
        $this->offset += $length;

        return $bytes;
    }
}
