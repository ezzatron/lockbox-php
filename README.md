# Lockbox for PHP

*Simple, strong encryption.*

[![Build Status]][Latest build]
[![Test Coverage]][Test coverage report]
[![Uses Semantic Versioning]][SemVer]

## Installation and documentation

* Available as [Composer] package [eloquent/lockbox].
* [API documentation] available.

## What is *Lockbox*?

*Lockbox* is the simplest possible way to implement strong, two-way, public-key
encryption for use in applications. *Lockbox* uses a combination of
well-established technologies to ensure the safety of data. For more
information, see the [Lockbox website].

## Usage

### Generating keys

Generating of keys is handled by the `openssl` command line tool (not part of
*Lockbox*). Generating a private 2048-bit RSA key in PEM format with no password
can be done with this command:

    openssl genrsa -out private.pem 2048

To create a key with a password, simply add the `-des3` flag, which will prompt
for password input before the key is created:

    openssl genrsa -des3 -out private.pem 2048

This private key must be kept secret, and treated as sensitive data. Private
keys are the only keys capable of decrypting data. Public keys, on the other
hand, are not as sensitive, and can be given to any party that will be
responsible for encrypting data.

*Lockbox* is capable of extracting public keys from private keys, there is no
need to create matching public key files; but if for some reason a public key
file is required, this command will create one (from an RSA key in this
example):

    openssl rsa -pubout -in private.pem -out public.pem

### Encrypting data

```php
use Eloquent\Lockbox\EncryptionCipher;
use Eloquent\Lockbox\Key\KeyFactory;

$data = 'Super secret data.';

$keyFactory = new KeyFactory;
$privateKey = $keyFactory->createPrivateKeyFromFile('/path/to/key.pem', 'password');
$publicKey = $privateKey->publicKey();

$cipher = new EncryptionCipher;
$encrypted = $cipher->encrypt($publicKey, $data);
```

### Encrypting multiple data packets with the same key

*Lockbox* includes 'bound' ciphers that are locked to a particular key. These
type of ciphers are convenient for encrypting multiple data packets.

```php
use Eloquent\Lockbox\BoundEncryptionCipher;
use Eloquent\Lockbox\Key\KeyFactory;

$data = array(
    'Super secret data.',
    'Extra secret data.',
    'Mega secret data.',
);

$keyFactory = new KeyFactory;
$privateKey = $keyFactory->createPrivateKeyFromFile('/path/to/key.pem', 'password');
$publicKey = $privateKey->publicKey();

$cipher = new BoundEncryptionCipher($publicKey);

$encrypted = array();
foreach ($data as $string) {
    $encrypted[] = $cipher->encrypt($string);
}
```

### Decrypting data

```php
use Eloquent\Lockbox\DecryptionCipher;
use Eloquent\Lockbox\Exception\DecryptionFailedException;
use Eloquent\Lockbox\Key\KeyFactory;

$encrypted = '<some encrypted data>';

$keyFactory = new KeyFactory;
$privateKey = $keyFactory->createPrivateKeyFromFile('/path/to/key.pem', 'password');

$cipher = new DecryptionCipher;

try {
    $data = $cipher->decrypt($privateKey, $encrypted);
} catch (DecryptionFailedException $e) {
    // decryption failed
}
```

### Decrypting multiple data packets with the same key

*Lockbox* includes 'bound' ciphers that are locked to a particular key. These
type of ciphers are convenient for decrypting multiple data packets.

```php
use Eloquent\Lockbox\BoundDecryptionCipher;
use Eloquent\Lockbox\Exception\DecryptionFailedException;
use Eloquent\Lockbox\Key\KeyFactory;

$encrypted = array(
    '<some encrypted data>',
    '<more encrypted data>',
    '<other encrypted data>',
);

$keyFactory = new KeyFactory;
$privateKey = $keyFactory->createPrivateKeyFromFile('/path/to/key.pem', 'password');

$cipher = new BoundDecryptionCipher($privateKey);

foreach ($encrypted as $string) {
    try {
        $data = $cipher->decrypt($string);
    } catch (DecryptionFailedException $e) {
        // decryption failed
    }
}
```

<!-- References -->

[Lockbox website]: http://lqnt.co/lockbox

[API documentation]: http://lqnt.co/lockbox-php/artifacts/documentation/api/
[Build Status]: https://api.travis-ci.org/eloquent/lockbox-php.png?branch=master
[Composer]: http://getcomposer.org/
[eloquent/lockbox]: https://packagist.org/packages/eloquent/lockbox
[Latest build]: https://travis-ci.org/eloquent/lockbox-php
[SemVer]: http://semver.org/
[Test coverage report]: https://coveralls.io/r/eloquent/lockbox-php
[Test Coverage]: https://coveralls.io/repos/eloquent/lockbox-php/badge.png?branch=master
[Uses Semantic Versioning]: http://b.repl.ca/v1/semver-yes-brightgreen.png
