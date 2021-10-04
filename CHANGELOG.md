## 2.3.1 - 2021-10-04

- Fixed type validation for issuedAt JWT field ([#56](https://github.com/GetStream/stream-chat-php/issues/56))
- Fixed license field in composer.json

## 2.3.0 - 2021-06-28

- Add support for new search improvements

## 2.2.0 - 2021-05-28

- Add support to revoke application and user level tokens
- Fix async constant initialization in a namespace in PHP 7.4+
- Upgrade deprecated cs-fixer config

## 2.1.0 - 2021-05-20

- Add query message flags support

## 2.0.0 - 2021-03-24

- Add channel partial update
- Ensure query channels has filters, empty filters not supported
- Fix test of get rate limits endpoint
- Move license to BSD-3
- Drop PHP 7.2 and PHP 7.4 and 8.0 support
- Upgrade composer to v2
- Move to github actions and use phan and php-cs-fixer in CI

## 1.4.0 - 2021-03-10

- Add get rate limits endpoint support

## 1.3.0 - 2020-12-21

- Add support for message filters in search
- Use post for query channels instead of get (deprecated in server)

## 1.2.0 - 2020-12-14

- Add channel mute support
- Add getCID helper to channel to concat type and id

## 1.1.10 - 2020-12-10

- Fix location setting while creating client
- Fix urls in file/image upload tests

## 1.1.9 - 2020-12-09

- Support empty filter call in queryMembers
- Fix user id access in member object after a fix in API

## 1.1.8 - 2020-09-10

- Support guzzle 7

## 1.1.7 - 2020-07-01

- add queryMembers implementation

## 1.1.6 - 2020-06-29

- Fixed chat settings

## 1.1.5 - 2019-12-19

- Add $clearHistory option to Channel->hide.

## 1.1.4 - 2019-10-29

- Check type of expiration in createToken and throw error if not a unix timestamp

## 1.1.3 - 2019-10-14

- Allow changing `$baseURL` with STREAM_BASE_CHAT_URL environment variable
