<?php

namespace App\Helpers;

/**
 * Agora RTC Token Builder — Access Token v1 ("006" prefix)
 *
 * Pure-PHP port of the official Agora PHP SDK token builder.
 * Reference: https://github.com/AgoraIO/Tools/tree/master/DynamicKey/AgoraDynamicKey/php
 */
class AgoraTokenBuilder
{
    const ROLE_PUBLISHER  = 1;
    const ROLE_SUBSCRIBER = 2;
    const ROLE_ADMIN      = 101;

    // Privilege keys
    const PRIVILEGE_JOIN_CHANNEL       = 1;
    const PRIVILEGE_PUBLISH_AUDIO      = 2;
    const PRIVILEGE_PUBLISH_VIDEO      = 3;
    const PRIVILEGE_PUBLISH_DATA       = 4;

    /**
     * Build an Agora RTC token.
     *
     * @param  string     $appId
     * @param  string     $appCertificate   Raw 32-character App Certificate string
     * @param  string     $channelName
     * @param  int        $uid              0 = wildcard
     * @param  int        $role             ROLE_PUBLISHER | ROLE_SUBSCRIBER
     * @param  int        $privilegeExpiredTs  Unix timestamp (seconds)
     * @return string
     */
    public static function buildTokenWithUid(
        string $appId,
        string $appCertificate,
        string $channelName,
        int $uid,
        int $role,
        int $privilegeExpiredTs
    ): string {
        // UID is empty string when 0 (Agora spec)
        $uidStr = $uid === 0 ? '' : (string) $uid;

        // Build privileges map
        $privileges = [self::PRIVILEGE_JOIN_CHANNEL => $privilegeExpiredTs];
        if ($role === self::ROLE_PUBLISHER) {
            $privileges[self::PRIVILEGE_PUBLISH_AUDIO] = $privilegeExpiredTs;
            $privileges[self::PRIVILEGE_PUBLISH_VIDEO] = $privilegeExpiredTs;
            $privileges[self::PRIVILEGE_PUBLISH_DATA]  = $privilegeExpiredTs;
        }

        // m = pack(salt uint32, expiredTs uint32) + packMapUint32(privileges)
        // Official Agora Access Token v1: NO version byte in message body.
        // ts must be the absolute expiry timestamp, not the current time.
        $salt = random_int(1, 0x7FFFFFFF);
        $m    = pack('VV', $salt, $privilegeExpiredTs) . self::packMapUint32($privileges);

        if (strlen($appId) !== 32) {
            throw new \RuntimeException('Invalid Agora App ID format. Expected 32 characters.');
        }

        if (strlen($appCertificate) !== 32) {
            throw new \RuntimeException('Invalid Agora App Certificate format. Expected 32 characters.');
        }

        // Signature: HMAC-SHA256 over (appId + channelName + uidStr + m)
        $signature = hash_hmac('sha256', $appId . $channelName . $uidStr . $m, $appCertificate, true);

        // CRC checksums
        $crcChannel = crc32($channelName) & 0xFFFFFFFF;
        $crcUid     = crc32($uidStr)     & 0xFFFFFFFF;

        // content = packString(signature) + pack(crcChannel, crcUid) + packString(m)
        $content = self::packString($signature) . pack('VV', $crcChannel, $crcUid) . self::packString($m);

        return '006' . $appId . base64_encode($content);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private static function packString(string $v): string
    {
        return pack('v', strlen($v)) . $v;
    }

    private static function packMapUint32(array $map): string
    {
        ksort($map);
        $out = pack('v', count($map));
        foreach ($map as $key => $val) {
            $out .= pack('vV', $key, $val);
        }
        return $out;
    }
}
