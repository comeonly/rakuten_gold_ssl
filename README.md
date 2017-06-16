# rakuten(楽天) gold ssl converter
Conver http to https in html files via ftp on rakuten gold server.

# Install

```bash
git clone https://github.com/comeonly/rakuten_gold_ssl
compose install
```

# Usage

```bash
php run.php <ftp_username> <ftp_password>
```

# Note
This script JUST replace `http://` to `https://`. So you can check target files and urls via `dry` option before convert.

```bash
php run.php <ftp_username> <ftp_password> dry
```
