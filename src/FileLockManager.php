<?php

namespace Procentr\FileLockManager;

use Procentr\FileLockManager\Exceptions\FileNotFoundException;
use Procentr\FileLockManager\Exceptions\LockException;
use Procentr\FileLockManager\Exceptions\UnlockException;
use RuntimeException;

class FileLockManager
{
    private string $filePath;
    // TODO: PHP 8.1+ supports native "mixed" type, but with PHP 8.0 let's make sure this doesn't break strict typing.
    private $fileHandle = null; // Using mixed type for PHP 8.0 compatibility, let's just go with it.
    private bool $isLocked = false;

    // Internal retry count for lock attempts
    private int $retryCount = 3; // How many retries do we want to allow? 3 seems fair for now.

    /**
     * Constructor.
     *
     * @param string $filePath Path to the file.
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function __construct(string $filePath)
    {
        /// Let's make sure we don't get an empty file path
        if (empty(trim($filePath))) {
            throw new \InvalidArgumentException('File path cannot be empty');
        }

        $this->filePath = $filePath;

        // Open the file. It will create one if it doesn't exist.
        // Using 'c+' gives read/write access.
        $this->fileHandle = @fopen($filePath, 'c+');
        if ($this->fileHandle === false) {
            throw new FileNotFoundException("Failed to open file: $filePath");
        }
    }

    // ... остальные методы ... а какие? ;-)

    /**
     * Tries to set a file lock with a timeout. We all hate waiting forever, right?
     *
     * @param int $type Type of the lock (exclusive or shared).
     * @param int $timeoutSeconds How long you're willing to wait (in seconds).
     * @return bool True if the lock was successfully set.
     * @throws LockException If we didn't make it on time.
     */
    public function lockWithTimeout(int $type, int $timeoutSeconds): bool
    {
        // Timeout must make some sense... so no negatives!
        if ($timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than zero');
        }

        $endTime = time() + $timeoutSeconds;
        $waitTime = 50000; // Why not start with 50ms? Seems reasonable, right?

        while (time() < $endTime) {
            if ($this->tryLock($type)) {
                return true; // Все получилось
            }

            // Exponential backoff with jitter
            $waitTime = min(200000, (int)($waitTime * (1.5 + (mt_rand(0, 10) / 100))));
            usleep($waitTime);
        }

        throw new LockException(
            "Failed to establish a lock on file within $timeoutSeconds seconds: $this->filePath"
        );
    }

    /**
     * Sets a lock on a file. Nothing terribly exciting here.
     *
     * @param int $type Lock type (LOCK_EX or LOCK_SH).
     * @param bool $blocking Block the process until lock is acquired? True or false.
     * @return bool Success of the operation.
     * @throws LockException If locking fails for some reason.
     */
    public function lock(int $type = LOCK_EX, bool $blocking = true): bool
    {
        $lockType = $type | ($blocking ? 0 : LOCK_NB);

        if ($this->fileHandle && flock($this->fileHandle, $lockType)) {
            $this->isLocked = true;

            return true;
        }

        throw new LockException("Couldn't set a lock on the file: $this->filePath");
    }

    /**
     * Releases a lock on the file. Think of it as 'unlocking'.
     *
     * @return bool If everything went OK and the lock was removed.
     * @throws UnlockException If something goes wrong.
     */
    public function unlock(): bool
    {
        if (!$this->isLocked) {
            throw new UnlockException("Trying to unlock a file that isn't locked: $this->filePath");
        }

        if ($this->fileHandle && flock($this->fileHandle, LOCK_UN)) {
            $this->isLocked = false;

            return true;
        }

        throw new UnlockException("Couldn't unlock the file: $this->filePath");
    }

    /**
     * Check if the file is currently locked by this instance.
     *
     * @return bool True if we have the lock; false otherwise.
     */
    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    /**
     * Attempts to set a non-blocking lock on the file.
     *
     * @param int $type Type of lock (LOCK_EX or LOCK_SH).
     * @return bool True if the lock was successfully set; false otherwise.
     */
    public function tryLock(int $type = LOCK_EX): bool
    {
        try {
            return $this->lock($type, false);
        } catch (LockException $e) {
            return false;
        }
    }

    /**
     * Destructor. Automatically removes the lock and closes the file.
     */
    public function __destruct()
    {
        // If the file is locked, let's unlock it.
        if ($this->isLocked) {
            try {
                $this->unlock(); // Be nice, release the lock.
            } catch (UnlockException $e) {
                // Ignore exceptions in destructors, no hard feelings.
            }
        }

        // Close the file handle if it's still open.
        if (is_resource($this->fileHandle)) {
            fclose($this->fileHandle);
        }
    }

}

/*
 * Груды забот —
 * но в снах уже шум прибоя.
 * Скоро — вода, смех.
 */