<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Exception;

class SmtpServiceManager
{
    private static $process = null;
    private static $isRunning = false;

    /**
     * Start SMTP catcher in background
     */
    public static function start()
    {
        if (self::$isRunning) {
            return;
        }

        try {
            // Start the SMTP catcher command in background
            $phpBinary = PHP_BINARY;
            $artisanPath = base_path('artisan');

            // For Windows
            if (PHP_OS_FAMILY === 'Windows') {
                $command = "start /B \"\" \"$phpBinary\" \"$artisanPath\" smtp:start";
                pclose(popen($command, 'r'));
            } else {
                // For Unix/Linux/Mac
                $command = "$phpBinary $artisanPath smtp:start > /dev/null 2>&1 &";
                exec($command);
            }

            self::$isRunning = true;

            // Wait a moment to ensure it starts
            usleep(500000); // 0.5 seconds

            return true;
        } catch (Exception $e) {
            logger()->error('Failed to start SMTP catcher: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Stop SMTP catcher
     */
    public static function stop()
    {
        if (!self::$isRunning) {
            return;
        }

        try {
            // Find and kill the SMTP process
            if (PHP_OS_FAMILY === 'Windows') {
                // Kill process listening on port 1025
                exec('netstat -ano | findstr :1025', $output);
                foreach ($output as $line) {
                    if (preg_match('/LISTENING\s+(\d+)/', $line, $matches)) {
                        $pid = $matches[1];
                        exec("taskkill /F /PID $pid");
                    }
                }
            } else {
                // For Unix/Linux/Mac
                exec("lsof -ti:1025 | xargs kill -9");
            }

            self::$isRunning = false;
            return true;
        } catch (Exception $e) {
            logger()->error('Failed to stop SMTP catcher: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if SMTP catcher is running
     */
    public static function isRunning()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec('netstat -ano | findstr :1025', $output);
            foreach ($output as $line) {
                if (strpos($line, 'LISTENING') !== false) {
                    self::$isRunning = true;
                    return true;
                }
            }
        } else {
            exec('lsof -ti:1025', $output);
            if (!empty($output)) {
                self::$isRunning = true;
                return true;
            }
        }

        self::$isRunning = false;
        return false;
    }

    /**
     * Restart SMTP catcher
     */
    public static function restart()
    {
        self::stop();
        sleep(1);
        return self::start();
    }
}
