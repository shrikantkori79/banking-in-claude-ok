# Banking software: project notes (hand-off for a new chat)

PHP 8 + MySQL (PDO), no framework. Served by Laragon at http://banking.test (project folder = web root).
Setup: copy the folder, run install.php once (first time) or upgrade.php (after updates), then delete both.
Settings in inc/config.php (database, APP_DEBUG, MAKER_CHECKER_DIFFERENT_USER). BASE_URL is detected automatically.
Layout rules are in assets/style.css; pages load assets with a version (asset() in inc/bootstrap.php).
Only the files in this zip are the source of truth. Nothing has been run by the assistant (no PHP in its environment); errors found by the user are fixed from screenshots.

## Apps and screens built
- Login (date, branch, user, password), launcher, user/branch settings (users, permissions, branches, day start/end), backup download.
- Master application (master/): Director, balance sheet head/sub head/account name, profit-loss head/account name,
  Share, Deposit (SB), Term (TD), CC, FD (same form as CC), Pigmy, RD and Loan masters (scheme.php, one page for all).
- Transaction application (transaction/): individual and company customers (customer.php), share/SB/TD/CC accounts (account_new.php),
  pigmy and RD accounts (pigrd_new.php), loan accounts (loan_new.php + loan_report.php), customer list.
- Cashier and passing (cashier/index.php): passes term and CC accounts (status 2 to 3).
- Not built: transactions/vouchers, account closing, deposit amounts, reports, fixed instalment loan account, master edit, transaction edit, pledge detail forms.

## Conventions
- Every master table has its own id; other tables store ids, never names.
- Legacy column names are kept where the user sent schemas (maihea, subhea, accnam, heaid, subheaid, accnum, clodat ...).
- clodat 01-Mar-1979 = open/active. Customers: newcustomer (custype 1 individual, 2 company), joicusdet (company directors), cuskyc, lookups newpla/newqua/newocc/newpri/newcas/newkyc.
- Unicode Kannada in the new database (legacy font-encoded Kannada is not converted, by user's choice).
- Back date entry needs permission p_backdate. Permissions: see PERMS in inc/bootstrap.php.

## Tables added (see sql/schema.sql)
Heads: balmaimas (typ 1 asset, 2 liability; pri_ord), balsubmas, balaccmas, prolosmaimas, prolosmas (heaid = prolosmaimas.id).
Masters: scheme_master (+ scheme_slab, scheme_comm, scheme_int, scheme_pledge, scheme_pl, scheme_bs). Master fees at share/SB creation are copied to depaccfee.
Deposit accounts: depacc + depaccjoi (heaid 0 share, 1 SB, 2 TD, 3 CC; subheaid = scheme_master.id), depacc_tmp + depaccjoi_tmp (pending, sta 2), accseq.
Pigmy/RD: newpigage (agents; heaid 0 pigmy, 1 RD; agents are customers), newpigacc, newrdacc, rdinst, pigseq. Account numbers run per agent.
Loans: newloa (other loans), newloa_dep, newloa_dep_list, newloa_sur_dir (surordir 1 surety, 0 director), newloa_pledge, loaseq. typofloa 0 term, 1 CC, 2 pigmy, 3 RD, 4 other.

## Rules the user gave
- Account opening: head hard coded; sub head from the matching master (CC list includes FD); customer under 18 needs a guardian; joint needs 2+ customers, non-joint exactly 1; term and CC go through maker/checker (temp tables, sta 2 then 3; a different user must pass).
- Share and SB creation applies the fees set in the Share / Deposit master.
- Voucher details, account closing and deposit period belong to the transaction phase.
- Pigmy/RD: need agents (create from the account screen if none); transactional detail only for RD; RD interest is added monthly at rate/12 (1000 x 12 months at 11.5% = 12774); no other data comes from the pigmy/RD masters.
- FD/CC slab: "times of deposit" blank = 0 (ask simple or compound at account opening), 2 doubles, 3 triples.
- Pigmy master: pay interest or receive commission when closing within a period range.
- Loans: deposit loans pledge the customer's open deposits not pledged to a running loan; loan %, int % and penal int % come from the deposit master; other loans use the loan master's interest; instalments day wise or monthly with "after every"; no gap instalment; report with instalment schedule after saving.

## Assumptions to confirm with the user
- Joint types 0 to 4 = Either or survivor, Anyone or survivor, Former or survivor, Latter or survivor, Jointly.
- gen 1 male, 2 female. Loan instalment type 1 monthly, 2 day wise. gurrel stored as relation text (legacy data shows 1979).
- Quarterly/half-yearly/yearly RD calculation, how Offer % applies, rounding of maturity amount.
- Table names guessed: newcustomer, cuskyc, newpigacc, newrdacc, rdinst, depacc/depaccjoi (one table for all heads).
- Deposit balances are not available until deposit transactions exist, so deposit loans cannot be saved yet (function deposit_balance in inc/loans.php).

## Next steps
1. Transaction phase (Transactions menu): voucher, deposits, loan disbursement/repayment, receipt/token numbers, closing, reports. Needs screens, schemas and rules.
2. Fixed instalment loan account.
3. Master edit and transaction edit applications.
