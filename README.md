# Mailer

Uses [adt/mail-api](https://github.com/appsdevteam/MailApi) to

1. use single mailing service for all your projects,
2. ensure higher email deliverability.

## Installation

composer:
```bash
composer require adt/mailer
```

config.neon:
```neon
extensions:
	adtMailer: ADT\Mailer\DI\AdtMailerExtension

adtMailer:
	remote:
		api: yourAdtMailApiInstance.com:1234

		# can be either static string or method, required
		key: yourPrivateKey

	error:
		# mode: silent => log and continue
		# mode: exception => throw 
		mode: silent

		# all undelivered messages are stored here (applies to mode: silent)
		logDir: %logDir%/adt_mailer

	# if recipient is suppressed, this address receives notification and delist link
	# can be either static string or method, required
	suppressionControlAddress: @App\Model\SuppressionControl::decide
```

## Usage

```php
// inject IMailer into $this->mailer

// create message
$message = new \Nette\Mail\Message;

// send message
$this->mailer->send($message);
```

### What happens "under the hood"?

1. Connection to [adt/mail-api](https://github.com/appsdevteam/MailApi) server is made.
2. Message is serialized and send over there.
3. If connecting/transmitting should fail, next step is determined by `error.mode` config:
  - `silent`: store mail into `error.logDir`, log using Tracy, and continue,
  - `exception`: exception is thrown without any logging
