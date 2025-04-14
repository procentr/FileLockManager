# FileLockManager 🚀

Hey there 👋! If you've ever struggled with managing file locks in a multi-process environment, this is the library for you! **FileLockManager** lets you handle file locks in PHP with ease. It's here to make sure your files are accessed safely and without chaos happening.

## What Can It Do?

Here’s why FileLockManager is pretty awesome:

- Super simple interface for file locking 🛠️
- Supports **exclusive** and **shared** locks
- Lets you try non-blocking lock attempts using `tryLock()` (quick and easy tests!)
- Provides lock timeouts via `lockWithTimeout()`
- Automatically creates the file if it doesn’t exist yet 🗂️
- Throws meaningful, human-readable exceptions (no cryptic errors!)
- Safe for both **threads** and **processes**
- Fully compatible with PHP 8.0+ ✅

## Installation 🔧

Getting started is as easy as running one command. Install it with Composer:

```bash
composer require procentr/file-lock-manager
```

---

## How to Use It

Below are some examples to help you get started. You'll see just how simple it is!

### Basic Example

Want to lock a file so no one else can mess with it while you’re working? Use this:

```php
use Procentr\FileLockManager\FileLockManager;

$lockManager = new FileLockManager('/path/to/file.txt');

// Lock the file for exclusive access 🚪🔐
$lockManager->lock();

try {
    // Do your stuff safely here
    file_put_contents('/path/to/file.txt', 'Important content!');
    
} finally {
    // Always unlock it, because sharing is caring ❤️
    $lockManager->unlock();
}
```

### Non-Blocking Lock

Need to know if you can lock a file without waiting around? Use `tryLock()`:

```php
use Procentr\FileLockManager\FileLockManager;

$lockManager = new FileLockManager('/path/to/file.txt');

if ($lockManager->tryLock()) {
    try {
        echo "Yay! The lock succeeded! 🎉";
        
    } finally {
        $lockManager->unlock();
    }
} else {
    echo "Oops... File is being used by someone else right now. 😕";
}
```

### Lock with Timeout

Don’t wanna wait forever? Use `lockWithTimeout()` and specify how long you’re willing to wait:

```php
use Procentr\FileLockManager\FileLockManager;
use Procentr\FileLockManager\Exceptions\LockException;

$lockManager = new FileLockManager('/path/to/file.txt');

try {
    // Wait for up to 5 seconds to get the lock ⏱️
    $lockManager->lockWithTimeout(LOCK_EX, 5);
    
    // Do whatever you need here! ✨
    
    $lockManager->unlock();
} catch (LockException $e) {
    echo "No luck getting the lock within the timeout! 😓: " . $e->getMessage();
}
```

### Shared Locks

If you just need to read a file and don’t care if others are reading too, use a **shared lock**:

```php
use Procentr\FileLockManager\FileLockManager;

$lockManager = new FileLockManager('/path/to/file.txt');

// Shared lock: Everyone’s invited to read! 📖
$lockManager->lock(LOCK_SH);

try {
    $content = file_get_contents('/path/to/file.txt');
    echo "Reading content safely: $content";

} finally {
    $lockManager->unlock();
}
```

### Check If Locked

Sometimes, you might just want to check if your current instance has the file locked already. Here’s how:

```php
$lockManager = new FileLockManager('/path/to/file.txt');

if (!$lockManager->isLocked()) {
    echo "The file is free! Go ahead and lock it. 🟢";
}

$lockManager->lock();
if ($lockManager->isLocked()) {
    echo "File is now locked. Mission accomplished! 🔒";
}

$lockManager->unlock();
```

---

## Error Handling 💡

What happens if something goes wrong? Don’t worry, **FileLockManager** throws clear, easy-to-understand exceptions:

### Example 1: File Not Found

```php
try {
    $lockManager = new FileLockManager('/invalid/path/file.txt');
} catch (FileNotFoundException $e) {
    echo "Could not find or create the file: " . $e->getMessage();
}
```

### Example 2: Unlock Without Locking

```php
try {
    $lockManager = new FileLockManager('/path/to/file.txt');
    $lockManager->unlock(); // Did you forget to lock first? 🤔
} catch (UnlockException $e) {
    echo "Unlock error: " . $e->getMessage();
}
```

### Example 3: Failing to Lock

```php
try {
    $lockManager->lockWithTimeout(LOCK_EX, 2);
} catch (LockException $e) {
    echo "Failed to lock the file: " . $e->getMessage();
}
```

---

### Summary ✨

FileLockManager gives you the tools to manage file locking with confidence, clarity, and ease. Stop worrying about race conditions and let this library help you focus on more exciting things in your code. Give it a try and see how simple it can be to tame file locks! 🦾