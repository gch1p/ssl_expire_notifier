# ssl_expire_notifier

Simple PHP script that checks

- SSL certificates expiration dates 
- domain registration expiration dates 

for a list of given domains
and notifies you via Telegram if some of them are about to expire.

Supposed to be run by cron daily or so.

## Configuration

Config file is expected to be at `~/.config/ssl_expire_notifier.ini`.

```ini
telegram_enabled = 1
telegram_token = "your_bot_token"
telegram_chat_id = "your_chat_id"

verbose = 1

ssl_warn_days = 14
ssl_error_days = 5

reg_warn_days = 30
reg_error_days = 15

hosts[] = example.org
hosts[] = mail.example.com:993
```


## License

MIT
