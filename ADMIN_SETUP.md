# Admin setup

1. Create an account through the normal sign-up page and confirm that it can sign in.
2. From PowerShell, promote that account using its username:

   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\dci_vault\dci_vault\promote-admin.php <username>
   ```

3. Open `admin-login.php` and sign in with that account. It will open the admin dashboard.

After signing in, use the Users page to create staff accounts and assign the Administrator role. Those accounts can also sign in through `admin-login.php`.

Customer and administrator sign-ins use separate PHP session cookies, so opening or refreshing one portal does not switch the other portal's signed-in account. Use the portal-specific logout link to end only that portal's session.

The promotion script only runs through PHP CLI; it returns 404 when requested over the web. It does not create a default password or expose public administrator registration. Run it only on a trusted machine.

For an existing database, run `admin-user-status-migration.sql` once to add the account active-status column. Fresh imports of `dci_db.sql` already include it; do not run the migration again after the column exists. Deactivating an account blocks sign-in but keeps its order history and relationships intact.

The `.html` admin URLs redirect to their protected `.php` pages. Apache must have `mod_rewrite` enabled and allow the project `.htaccess` rules.
