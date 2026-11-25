# Banking software (PHP + MySQL)

## Run on Laragon
1. Copy this folder to `C:\laragon\www\banking`
2. Start Laragon (Apache + MySQL)
3. Open http://localhost/banking/install.php once. It creates database `banking`, all tables, branch 011 and user BANK (password `Change@123`).
4. Delete install.php, open http://localhost/banking/ and sign in.
5. Change DB settings or folder name in `inc/config.php` if yours differ.

## Layout
- `index.php` login (date, branch, user, password) -> `launcher.php` main menu
- `user_branch.php` create/edit user, permissions, branches, day start/end
- `master/` all master settings forms (director, balance sheet heads, profit-loss heads, share/deposit/term/FD/CC/pigmy/RD/loan)
- `transaction/`, `cashier/`, `master_edit/`, `transaction_edit/` placeholders for next phases
- `sql/schema.sql` table definitions

## Updating from the first version
Open `http://localhost/banking/upgrade.php` once while signed in. It replaces the old generic `scheme_master` table with the new scheme tables (the old one held no real data), then delete upgrade.php.
