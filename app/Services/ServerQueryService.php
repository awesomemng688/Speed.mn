<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Throwable;

class ServerQueryService
{
    public function query(Server $server): array
    {
        $started = hrtime(true);
        $socket = null;
        try {
            $socket = @fsockopen('udp://'.$server->ip, $server->port, $errorCode, $errorMessage, 2);
            if (!is_resource($socket)) {
                return $this->offline($started, "UDP connection failed ({$errorCode}): {$errorMessage}");
            }

            stream_set_timeout($socket, 2);
            $payload = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";
            fwrite($socket, $payload);
            $response = fread($socket, 4096);

            if ($response === false || strlen($response) < 6 || substr($response, 0, 4) !== "\xFF\xFF\xFF\xFF") {
                $timedOut = stream_get_meta_data($socket)['timed_out'] ?? false;
                return $this->offline($started, $timedOut ? 'A2S query timed out' : 'No valid A2S response received', $socket);
            }
            if (ord($response[4]) === 0x41) {
                $challenge = substr($response, 5, 4);
                if (strlen($challenge) !== 4) {
                    return $this->offline($started, 'A2S challenge packet was incomplete', $socket);
                }
                fwrite($socket, $payload.$challenge);
                $response = fread($socket, 4096);
                if (!is_string($response) || strlen($response) < 6 || substr($response, 0, 4) !== "\xFF\xFF\xFF\xFF") {
                    $timedOut = stream_get_meta_data($socket)['timed_out'] ?? false;
                    return $this->offline($started, $timedOut ? 'A2S challenge response timed out' : 'A2S challenge response was invalid', $socket);
                }
            }

            $offset = 5;
            $this->readByte($response, $offset); // protocol
            $name = $this->readString($response, $offset);
            $map = $this->readString($response, $offset);
            $this->readString($response, $offset); // folder
            $this->readString($response, $offset); // game
            $this->readUInt16($response, $offset); // app id
            $players = $this->readByte($response, $offset);
            $maxPlayers = $this->readByte($response, $offset);
            $bots = $this->readByte($response, $offset);
            $this->readByte($response, $offset); // server type
            $this->readByte($response, $offset); // environment
            $this->readByte($response, $offset); // visibility
            $vac = $this->readByte($response, $offset);
            $version = $this->readString($response, $offset);
            $playerList = $this->queryPlayers($socket);
            fclose($socket);

            return [
                'online' => true,
                'players' => $players,
                'max_players' => $maxPlayers ?: ($server->max_players ?: null),
                'bots' => $bots,
                'vac' => $vac === null ? null : (bool) $vac,
                'map' => $map ?: null,
                'response_time' => (int) round((hrtime(true) - $started) / 1_000_000),
                'player_list' => $playerList,
                'version' => $version ?: null,
                'query_succeeded' => true,
                'query_error' => null,
            ];
        } catch (Throwable $exception) {
            if (is_resource($socket)) {
                fclose($socket);
            }
            Log::warning('Server query failed', [
                'server_id' => $server->id,
                'address' => $server->address,
                'error' => $exception->getMessage(),
            ]);

            return $this->offline($started, 'A2S query exception: '.$exception->getMessage());
        }

    }

    private function queryPlayers($socket): array
    {
        $request = "\xFF\xFF\xFF\xFFU\xFF\xFF\xFF\xFF";
        fwrite($socket, $request);
        $response = fread($socket, 4096);
        if (!is_string($response) || strlen($response) < 9) {
            return [];
        }

        $offset = 4;
        if (ord($response[$offset++]) === 0x41) {
            $challenge = substr($response, $offset, 4);
            if (strlen($challenge) !== 4) {
                return [];
            }
            fwrite($socket, "\xFF\xFF\xFF\xFFU".$challenge);
            $response = fread($socket, 8192);
        }
        if (!is_string($response) || strlen($response) < 5 || substr($response, 0, 4) !== "\xFF\xFF\xFF\xFF" || ord($response[4]) !== 0x44) {
            return [];
        }

        $offset = 5;
        $count = $this->readByte($response, $offset) ?? 0;
        $players = [];
        for ($i = 0; $i < $count; $i++) {
            $index = $this->readByte($response, $offset);
            $name = $this->readString($response, $offset);
            $score = $this->readInt32($response, $offset);
            $duration = $this->readFloat($response, $offset);
            if ($index === null || $name === null || $score === null || $duration === null) {
                break;
            }
            $players[] = ['index' => $index, 'name' => $name, 'score' => $score, 'duration' => $duration];
        }

        return $players;
    }

    private function readByte(string $response, int &$offset): ?int
    {
        if ($offset >= strlen($response)) {
            return null;
        }
        return ord($response[$offset++]);
    }

    private function readUInt16(string $response, int &$offset): ?int
    {
        $value = substr($response, $offset, 2);
        if (strlen($value) !== 2) {
            $offset = strlen($response);
            return null;
        }
        $offset += 2;
        return unpack('v', $value)[1];
    }

    private function readInt32(string $response, int &$offset): ?int
    {
        $value = substr($response, $offset, 4);
        if (strlen($value) !== 4) {
            $offset = strlen($response);
            return null;
        }
        $offset += 4;
        return unpack('l', $value)[1];
    }

    private function readFloat(string $response, int &$offset): ?float
    {
        $value = substr($response, $offset, 4);
        if (strlen($value) !== 4) {
            $offset = strlen($response);
            return null;
        }
        $offset += 4;
        return unpack('g', $value)[1];
    }

    private function readString(string $response, int &$offset): ?string
    {
        if ($offset >= strlen($response)) {
            return null;
        }
        $end = strpos($response, "\0", $offset);
        if ($end === false) {
            $offset = strlen($response);
            return null;
        }
        $value = substr($response, $offset, $end - $offset);
        $offset = $end + 1;
        return $value;
    }

    private function offline(int $started, string $error, $socket = null): array
    {
        if (is_resource($socket)) {
            fclose($socket);
        }

        return [
            'online' => false,
            'players' => 0,
            'max_players' => null,
            'bots' => null,
            'vac' => null,
            'map' => null,
            'response_time' => (int) round((hrtime(true) - $started) / 1_000_000),
            'player_list' => [],
            'version' => null,
            'query_succeeded' => false,
            'query_error' => $error,
        ];
    }
}
