<?php

class PsSocialfollowChildProcess
{
    const SENTINEL = 'SENTINEL_REACHED';

    const DIAGNOSTIC_PATTERN = '/(?:^|\n)(?:PHP\s+)?(?:Notice|Warning|Deprecated|Strict Standards|Parse error|Fatal error):/i';

    /**
     * @param string $scriptBody PHP code to run after bootstrap ini (no closing tag)
     * @return array{exitCode:int,stdout:string,stderr:string}
     */
    public static function run($scriptBody)
    {
        $php = getenv('PHP_BINARY');
        if (!$php) {
            $php = PHP_BINARY;
        }

        $wrapper = '<?php '
            . 'error_reporting(-1); ini_set("display_errors", "1"); ini_set("display_startup_errors", "1"); '
            . $scriptBody . ' echo "' . self::SENTINEL . '";';

        $tmp = tempnam(sys_get_temp_dir(), 'psf-child-');
        if ($tmp === false) {
            throw new RuntimeException('Could not create temp script');
        }
        $path = $tmp . '.php';
        rename($tmp, $path);
        file_put_contents($path, $wrapper);

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $cmd = escapeshellarg($php)
            . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 '
            . escapeshellarg($path);

        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            @unlink($path);
            throw new RuntimeException('proc_open failed');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        @unlink($path);

        return array(
            'exitCode' => (int) $exitCode,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
        );
    }

    /**
     * @param array{exitCode:int,stdout:string,stderr:string} $result
     */
    public static function assertCleanGuardExit($result)
    {
        PHPUnit_Framework_Assert::assertSame(0, $result['exitCode'], 'exit code: ' . $result['stderr'] . $result['stdout']);
        PHPUnit_Framework_Assert::assertNotContains(self::SENTINEL, $result['stdout'] . $result['stderr']);
        PHPUnit_Framework_Assert::assertNotRegExp(self::DIAGNOSTIC_PATTERN, $result['stdout'] . $result['stderr']);
    }

    /**
     * @param string $absolutePath
     */
    public static function includeGuardFile($absolutePath)
    {
        $escaped = str_replace('\\', '\\\\', $absolutePath);
        return self::run('include ' . var_export($absolutePath, true) . ';');
    }
}
