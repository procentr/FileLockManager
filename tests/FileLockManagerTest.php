<?php

namespace Procentr\FileLockManager\Tests;

use Procentr\FileLockManager\FileLockManager;
use Procentr\FileLockManager\Exceptions\FileNotFoundException;
use Procentr\FileLockManager\Exceptions\LockException;
use Procentr\FileLockManager\Exceptions\UnlockException;
use PHPUnit\Framework\TestCase;

class FileLockManagerTest extends TestCase
{
    private string $testFilePath;
    private FileLockManager $lockManager;

    protected function setUp(): void
    {
        // Create a temporary file for testing
        $this->testFilePath = sys_get_temp_dir().'/file_lock_manager_test_'.uniqid().'.txt';
        file_put_contents($this->testFilePath, 'Test content');
        $this->lockManager = new FileLockManager($this->testFilePath);
    }

    protected function tearDown(): void
    {
        // Clean up temporary file after tests
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
    }

    public function testConstructor()
    {
        // Test successful initialization
        $manager = new FileLockManager($this->testFilePath);
        $this->assertInstanceOf(FileLockManager::class, $manager);

        // Test with non-existent file (should create the file)
        $nonExistentPath = sys_get_temp_dir().'/non_existent_file_'.uniqid().'.txt';
        $manager = new FileLockManager($nonExistentPath);
        $this->assertInstanceOf(FileLockManager::class, $manager);
        $this->assertFileExists($nonExistentPath);
        unlink($nonExistentPath);

        // Test with invalid file path
        $this->expectException(FileNotFoundException::class);
        new FileLockManager('/invalid/path/that/cannot/be/written/to/file.txt');
    }

    public function testLock()
    {
        $result = $this->lockManager->lock();
        $this->assertTrue($result);
        $this->assertTrue($this->lockManager->isLocked());
    }

    public function testUnlock()
    {
        $this->lockManager->lock();
        $result = $this->lockManager->unlock();
        $this->assertTrue($result);
        $this->assertFalse($this->lockManager->isLocked());
    }

    public function testUnlockWithoutLock()
    {
        $this->expectException(UnlockException::class);
        $this->lockManager->unlock();
    }

    public function testIsLocked()
    {
        $this->assertFalse($this->lockManager->isLocked());
        $this->lockManager->lock();
        $this->assertTrue($this->lockManager->isLocked());
        $this->lockManager->unlock();
        $this->assertFalse($this->lockManager->isLocked());
    }

    public function testTryLock()
    {
        // First attempt should succeed
        $result = $this->lockManager->tryLock();
        $this->assertTrue($result);
        $this->assertTrue($this->lockManager->isLocked());

        // Create another instance to test concurrent access
        $anotherManager = new FileLockManager($this->testFilePath);
        $result = $anotherManager->tryLock();
        $this->assertFalse($result);
        $this->assertFalse($anotherManager->isLocked());
    }

    public function testLockWithTimeout()
    {
        // Lock should succeed immediately
        $result = $this->lockManager->lockWithTimeout(LOCK_EX, 1);
        $this->assertTrue($result);
        $this->assertTrue($this->lockManager->isLocked());

        // Create another manager pointing to the same file
        $anotherManager = new FileLockManager($this->testFilePath);

        // This should time out and throw exception
        $this->expectException(LockException::class);
        $anotherManager->lockWithTimeout(LOCK_EX, 1);
    }

    public function testLockWithSharedAccess()
    {
        // First manager gets shared lock
        $result = $this->lockManager->lock(LOCK_SH);
        $this->assertTrue($result);
        $this->assertTrue($this->lockManager->isLocked());

        // Second manager should be able to get a shared lock too
        $anotherManager = new FileLockManager($this->testFilePath);
        $result = $anotherManager->lock(LOCK_SH);
        $this->assertTrue($result);
        $this->assertTrue($anotherManager->isLocked());

        // Third manager trying to get exclusive lock should fail
        $thirdManager = new FileLockManager($this->testFilePath);
        $result = $thirdManager->tryLock(LOCK_EX);
        $this->assertFalse($result);
    }

    public function testDestructorUnlocksFile()
    {
        // Lock the file
        $this->lockManager->lock();
        $this->assertTrue($this->lockManager->isLocked());

        // Force the destructor to run
        unset($this->lockManager);

        // Create a new manager and check if we can acquire a lock
        $newManager = new FileLockManager($this->testFilePath);
        $result = $newManager->tryLock();
        $this->assertTrue($result);

        // Reset the lock manager for tearDown()
        $this->lockManager = $newManager;
    }

    // А вы что - правда вот все это читаете? (°o°) Может еще и пользуетесь?
    // Если да - напишите мне на gogi@procentr.org - мне будет приятно

    public function testConcurrentAccess()
    {
        // This is a more complex test simulating concurrent access
        // using multiple processes if possible

        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available, skipping concurrent access test');

            return;
        }

        // Fork a child process
        $pid = pcntl_fork();

        if ($pid == -1) {
            // Fork failed
            $this->fail('Could not fork process');
        } else {
            if ($pid) {
                // Parent process
                $this->lockManager->lock();
                $this->assertTrue($this->lockManager->isLocked());

                // Give child process time to try to lock
                sleep(2);

                // Unlock
                $this->lockManager->unlock();

                // Wait for child to complete
                pcntl_wait($status);
            } else {
                // Child process
                $childManager = new FileLockManager($this->testFilePath);

                // Give parent time to acquire lock
                sleep(1);

                // Try non-blocking lock which should fail
                $result = $childManager->tryLock();
                if ($result === true) {
                    exit(1); // This should not happen
                }

                // Wait for parent to release lock
                sleep(2);

                // Now we should be able to lock
                $result = $childManager->tryLock();
                exit($result ? 0 : 1);
            }
        }
    }
}