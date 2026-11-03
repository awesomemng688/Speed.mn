# Server hardening checklist (Ubuntu VPS)

Run on the VPS. Keep a second SSH session open while changing SSH or firewall rules.

## 1. SSH key login
On your PC: `ssh-keygen -t ed25519`, then `ssh-copy-id root@speed.mn` (or append the public key to `~/.ssh/authorized_keys`).
Confirm key login works in a new session, then in `/etc/ssh/sshd_config` set:

```
PasswordAuthentication no
PermitRootLogin prohibit-password
```

Apply with `sudo systemctl reload ssh`.

## 2. Firewall (ufw)
Allow SSH first, then web and the game ports you use. Add Pterodactyl/game ports as needed.

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80,443/tcp
sudo ufw allow 27015/tcp
sudo ufw allow 27015/udp
sudo ufw enable
sudo ufw status verbose
```

Do not open 3306 (MySQL).

## 3. Restrict MySQL
The rank databases are read from the same VPS, so use `127.0.0.1` for every `RANK_*_DB_HOST` in `.env`.
Then limit accounts to localhost and make MySQL listen locally only:

```sql
RENAME USER 'speed_user'@'%' TO 'speed_user'@'127.0.0.1';
RENAME USER 'admin_user'@'%' TO 'admin_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

In `/etc/mysql/mariadb.conf.d/50-server.cnf` set `bind-address = 127.0.0.1` and run `sudo systemctl restart mariadb`.
If Pterodactyl containers (`172.17.%.%`) or an external game server need the database, keep only those specific hosts. Verify from outside: `nmap -p 3306 speed.mn` must show the port closed or filtered.

After changing `.env` run `sudo -u www-data php artisan config:cache`.
