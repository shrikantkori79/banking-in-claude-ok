CREATE TABLE IF NOT EXISTS branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  brcod VARCHAR(5) NOT NULL UNIQUE,
  name_en VARCHAR(150) NOT NULL,
  name_kn VARCHAR(150) NULL,
  incharge VARCHAR(100) NULL,
  place VARCHAR(100) NULL,
  contact VARCHAR(30) NULL,
  address VARCHAR(255) NULL,
  voucher_print TINYINT(1) NOT NULL DEFAULT 0,
  passing_perm TINYINT(1) NOT NULL DEFAULT 0,
  otp_login TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  brcod VARCHAR(5) NOT NULL,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  mobile VARCHAR(15) NULL,
  totp_secret VARCHAR(64) NULL,
  p_dateclose TINYINT(1) NOT NULL DEFAULT 0,
  p_backdate TINYINT(1) NOT NULL DEFAULT 0,
  p_edit TINYINT(1) NOT NULL DEFAULT 0,
  p_editvoucher TINYINT(1) NOT NULL DEFAULT 0,
  p_passing TINYINT(1) NOT NULL DEFAULT 0,
  p_cashier TINYINT(1) NOT NULL DEFAULT 0,
  p_master TINYINT(1) NOT NULL DEFAULT 0,
  p_account TINYINT(1) NOT NULL DEFAULT 0,
  p_transaction TINYINT(1) NOT NULL DEFAULT 0,
  p_user TINYINT(1) NOT NULL DEFAULT 0,
  p_branch TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_branch_user (brcod, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS daymas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  brcod VARCHAR(5) NOT NULL,
  workdate DATE NOT NULL,
  status ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
  opened_by INT NULL,
  closed_by INT NULL,
  UNIQUE KEY uq_day (brcod, workdate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS desmas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Director master: legacy columns dirnam, dirnamkan, desid, JOIDAT, ADDR, MOBNUM, ID, allbra, BRACOD, sta, enddat
CREATE TABLE IF NOT EXISTS director (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dirnam VARCHAR(120) NOT NULL,
  dirnamkan VARCHAR(120) NULL,
  desid INT NULL,
  JOIDAT DATE NULL,
  ADDR VARCHAR(255) NULL,
  MOBNUM VARCHAR(15) NULL,
  allbra TINYINT(1) NOT NULL DEFAULT 0,
  BRACOD VARCHAR(5) NULL,
  sta CHAR(1) NOT NULL DEFAULT 'A',
  enddat DATE NULL,
  KEY (desid), KEY (BRACOD)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Balance sheet: every table keeps its own id; children store the parent's id (maiid, subid)
CREATE TABLE IF NOT EXISTS balmaimas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  maihea VARCHAR(120) NOT NULL,
  maiheakan VARCHAR(120) NULL,
  typ TINYINT NOT NULL DEFAULT 1,      -- 1 = Assets, 2 = Liabilities
  pri_ord INT NOT NULL DEFAULT 0       -- print order on the balance sheet
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS balsubmas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  maiid INT NOT NULL,                  -- balmaimas.id
  subhea VARCHAR(120) NOT NULL,
  subheakan VARCHAR(120) NULL,
  KEY (maiid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS balaccmas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  maiid INT NOT NULL,                  -- balmaimas.id
  subid INT NOT NULL,                  -- balsubmas.id
  accnam VARCHAR(120) NOT NULL,
  accnamkan VARCHAR(120) NULL,
  KEY (maiid), KEY (subid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prolosmaimas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  maihea VARCHAR(120) NOT NULL,
  maiheakan VARCHAR(120) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prolosmas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  heaid INT NOT NULL,                  -- prolosmaimas.id
  accnam VARCHAR(120) NOT NULL,
  accnamkan VARCHAR(120) NULL,
  KEY (heaid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scheme masters (share, deposit/SB, term/TD, cc, rd, pigmy, fd, loan). Header row; children store scheme_id = scheme_master.id
CREATE TABLE IF NOT EXISTS scheme_master (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('share','deposit','term','fd','cc','rd','pigmy','loan') NOT NULL,
  name VARCHAR(120) NOT NULL,
  namekan VARCHAR(120) NULL,
  facevalue DECIMAL(14,2) NOT NULL DEFAULT 0,
  bal_cheque DECIMAL(14,2) NOT NULL DEFAULT 0,
  bal_nocheque DECIMAL(14,2) NOT NULL DEFAULT 0,
  min_tran DECIMAL(14,2) NOT NULL DEFAULT 0,
  max_cash_tran DECIMAL(14,2) NOT NULL DEFAULT 0,
  min_days INT NOT NULL DEFAULT 0,
  intrate DECIMAL(6,2) NOT NULL DEFAULT 0,
  penalrate DECIMAL(6,2) NOT NULL DEFAULT 0,
  period_months INT NOT NULL DEFAULT 0,
  intappdate DATE NULL,
  loan_pct DECIMAL(6,2) NOT NULL DEFAULT 0,
  loan_int DECIMAL(6,2) NOT NULL DEFAULT 0,
  loan_pen DECIMAL(6,2) NOT NULL DEFAULT 0,
  auto_renew TINYINT(1) NOT NULL DEFAULT 0,
  int_after_mat DECIMAL(6,2) NOT NULL DEFAULT 0,
  mat_days INT NOT NULL DEFAULT 0,
  premat_ded DECIMAL(6,2) NOT NULL DEFAULT 0,
  premat_typeid INT NULL,
  def_days INT NOT NULL DEFAULT 0,
  def_pen DECIMAL(14,2) NOT NULL DEFAULT 0,
  def_pen_pct TINYINT(1) NOT NULL DEFAULT 0,
  pledge_compulsory TINYINT(1) NOT NULL DEFAULT 0,
  pig_mode TINYINT NOT NULL DEFAULT 1,        -- pigmy: 1 pay interest, 2 receive commission
  calc_daybase TINYINT(1) NOT NULL DEFAULT 0,
  calc_production TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_kind_name (kind, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scheme_slab (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scheme_id INT NOT NULL,
  slabname VARCHAR(60) NULL,
  from_m INT NOT NULL DEFAULT 0,
  from_d INT NOT NULL DEFAULT 0,
  to_m INT NOT NULL DEFAULT 0,
  to_d INT NOT NULL DEFAULT 0,
  times INT NOT NULL DEFAULT 0,
  intrate DECIMAL(6,2) NOT NULL DEFAULT 0,
  KEY (scheme_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scheme_comm (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scheme_id INT NOT NULL,
  minamt DECIMAL(14,2) NOT NULL DEFAULT 0,
  maxamt DECIMAL(14,2) NOT NULL DEFAULT 0,
  pct DECIMAL(6,2) NOT NULL DEFAULT 0,
  KEY (scheme_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- accounts a scheme posts to, with the amount (fee / charge) for each
CREATE TABLE IF NOT EXISTS scheme_pl (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scheme_id INT NOT NULL,
  heaid INT NOT NULL,           -- prolosmaimas.id
  proid INT NOT NULL,           -- prolosmas.id
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_s_pl (scheme_id, proid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scheme_bs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scheme_id INT NOT NULL,
  maiid INT NOT NULL,           -- balmaimas.id
  subid INT NOT NULL,           -- balsubmas.id
  accid INT NOT NULL,           -- balaccmas.id
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_s_bs (scheme_id, accid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- loan interest names: inttyp 1 = simple, 2 = penal
CREATE TABLE IF NOT EXISTS scheme_int (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scheme_id INT NOT NULL,
  intname VARCHAR(60) NOT NULL,
  inttyp TINYINT NOT NULL DEFAULT 1,
  intrate DECIMAL(6,2) NOT NULL DEFAULT 0,
  app_after INT NOT NULL DEFAULT 0,
  effdate DATE NULL,
  KEY (scheme_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pledge types required by a loan (pledgeid 1..8, fixed list in the app)
CREATE TABLE IF NOT EXISTS scheme_pledge (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scheme_id INT NOT NULL,
  pledgeid INT NOT NULL,
  UNIQUE KEY uq_s_pledge (scheme_id, pledgeid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== Customers (transaction application) =====
-- lookup tables keep the legacy names; every customer column stores the lookup id
CREATE TABLE IF NOT EXISTS newpla (id INT AUTO_INCREMENT PRIMARY KEY, planam VARCHAR(100) NOT NULL, kanplanam VARCHAR(100) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS newqua (id INT AUTO_INCREMENT PRIMARY KEY, quanam VARCHAR(100) NOT NULL, kanquanam VARCHAR(100) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS newocc (id INT AUTO_INCREMENT PRIMARY KEY, occnam VARCHAR(100) NOT NULL, kanoccnam VARCHAR(100) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS newpri (id INT AUTO_INCREMENT PRIMARY KEY, prinam VARCHAR(100) NOT NULL, kanprinam VARCHAR(100) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS newcas (id INT AUTO_INCREMENT PRIMARY KEY, casnam VARCHAR(100) NOT NULL, kancasnam VARCHAR(100) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS newkyc (id INT AUTO_INCREMENT PRIMARY KEY, kycnam VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- individual and company customers share one table (custype 1 = individual, 2 = company)
CREATE TABLE IF NOT EXISTS newcustomer (
  cusid INT AUTO_INCREMENT PRIMARY KEY,
  custype TINYINT NOT NULL DEFAULT 1,
  cusnam VARCHAR(150) NOT NULL,
  cusnamkan VARCHAR(150) NULL,
  plaid INT NOT NULL DEFAULT 0,        -- newpla.id
  occid INT NOT NULL DEFAULT 0,        -- newocc.id
  quaid INT NOT NULL DEFAULT 0,        -- newqua.id
  priid INT NOT NULL DEFAULT 0,        -- newpri.id
  casid INT NOT NULL DEFAULT 0,        -- newcas.id
  gen TINYINT NOT NULL DEFAULT 0,      -- 1 male, 2 female
  dob DATE NULL,
  joidat DATE NOT NULL,
  mobnum VARCHAR(15) NULL,
  emaid VARCHAR(100) NULL,
  add1 VARCHAR(100) NULL,
  add2 VARCHAR(100) NULL,
  add3 VARCHAR(100) NULL,
  intnum INT NOT NULL DEFAULT 0,       -- introducer customer id, 0 = none
  nom VARCHAR(100) NULL,
  rel VARCHAR(50) NULL,
  dirid INT NOT NULL DEFAULT 0,        -- director.id
  brcod VARCHAR(5) NULL,
  photo VARCHAR(100) NULL,
  sign VARCHAR(100) NULL,
  created_by INT NULL,
  KEY (custype), KEY (cusnam)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- directors of a company customer (cusid = newcustomer.cusid)
CREATE TABLE IF NOT EXISTS joicusdet (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cusid INT NOT NULL,
  cusnam VARCHAR(150) NOT NULL,
  cusnamkan VARCHAR(150) NULL,
  plaid INT NOT NULL DEFAULT 0,
  occid INT NOT NULL DEFAULT 0,
  quaid INT NOT NULL DEFAULT 0,
  priid INT NOT NULL DEFAULT 0,
  casid INT NOT NULL DEFAULT 0,
  gen TINYINT NOT NULL DEFAULT 1,
  dob DATE NULL,
  mobnum VARCHAR(15) NULL,
  traper TINYINT(1) NOT NULL DEFAULT 0,  -- transaction permission
  DESI INT NOT NULL DEFAULT 0,           -- desmas.id (designation)
  photo VARCHAR(100) NULL,
  sign VARCHAR(100) NULL,
  KEY (cusid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cuskyc (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cusid INT NOT NULL,
  kycid INT NOT NULL,                  -- newkyc.id
  kycdet VARCHAR(60) NOT NULL,
  expdat DATE NULL,
  KEY (cusid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== Deposit accounts: share (heaid 0), SB/current/others (1), term (2), CC/FD (3) =====
-- subheaid = scheme_master.id. Holders are linked by (heaid, subheaid, accnum), as in the legacy tables.
-- Term and CC accounts are first saved in the *_tmp tables with sta = 2 (pending) and move here with sta = 3 when passed.
CREATE TABLE IF NOT EXISTS depacc (
  id INT AUTO_INCREMENT PRIMARY KEY,
  heaid TINYINT NOT NULL,
  subheaid INT NOT NULL,
  accnum INT NOT NULL,
  joi TINYINT(1) NOT NULL DEFAULT 0,
  typofjoi TINYINT NOT NULL DEFAULT -1,      -- -1 not joint, 0..4 see JOINT_TYPES
  clodat DATE NOT NULL DEFAULT '1979-03-01', -- 01-Mar-1979 = account open
  entdatope DATE NULL,
  enttimope TIME NULL,
  useidcre INT NULL,
  entdatclo DATE NULL,
  enttimclo TIME NULL,
  useidclo INT NULL,
  resnoope VARCHAR(20) NULL,                 -- receipt no at opening
  resnoclo VARCHAR(20) NULL,                 -- receipt no at closing
  accdat DATE NOT NULL,
  dirid INT NOT NULL DEFAULT 0,
  cerno INT NOT NULL DEFAULT 1,
  sta TINYINT NOT NULL DEFAULT 3,            -- 2 pending, 3 completed
  closta TINYINT NOT NULL DEFAULT 0,
  loasta TINYINT NOT NULL DEFAULT 0,
  sb_id INT NOT NULL DEFAULT 0,
  toacc INT NOT NULL DEFAULT 0,
  brcod VARCHAR(5) NULL,
  passed_by INT NULL,
  passed_at DATETIME NULL,
  UNIQUE KEY uq_acc (heaid, subheaid, accnum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS depaccjoi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  heaid TINYINT NOT NULL,
  subheaid INT NOT NULL,
  accnum INT NOT NULL,
  cusid INT NOT NULL,
  nom VARCHAR(100) NULL,
  rel VARCHAR(50) NULL,
  gurid INT NOT NULL DEFAULT 0,
  gurrel VARCHAR(50) NULL,
  cerno INT NOT NULL DEFAULT 1,
  UNIQUE KEY uq_holder (heaid, subheaid, accnum, cusid),
  KEY (cusid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS depacc_tmp LIKE depacc;
CREATE TABLE IF NOT EXISTS depaccjoi_tmp LIKE depaccjoi;

-- last account number given for each head and sub head
CREATE TABLE IF NOT EXISTS accseq (
  heaid TINYINT NOT NULL,
  subheaid INT NOT NULL,
  last INT NOT NULL,
  PRIMARY KEY (heaid, subheaid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- fees applied when a share or SB account is opened, copied from the master's linked accounts (ids only)
-- src 'pl': maiid = prolosmaimas.id, accid = prolosmas.id        src 'bs': maiid = balmaimas.id, subid = balsubmas.id, accid = balaccmas.id
CREATE TABLE IF NOT EXISTS depaccfee (
  id INT AUTO_INCREMENT PRIMARY KEY,
  heaid TINYINT NOT NULL,
  subheaid INT NOT NULL,
  accnum INT NOT NULL,
  src CHAR(2) NOT NULL,
  maiid INT NOT NULL,
  subid INT NOT NULL DEFAULT 0,
  accid INT NOT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  KEY (heaid, subheaid, accnum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== Pigmy and RD accounts (heaid here: 0 = pigmy, 1 = RD) =====
-- newpigage: agents of both pigmy (heaid 0) and RD (heaid 1); agents are customers.
-- Agent numbers run per head and sub head, and account numbers run per agent
CREATE TABLE IF NOT EXISTS newpigage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  heaid TINYINT NOT NULL,
  subheaid INT NOT NULL,                       -- scheme_master.id
  agenum INT NOT NULL,
  cusid INT NOT NULL,                          -- newcustomer.cusid
  joidat DATE NULL,
  clodat DATE NOT NULL DEFAULT '1979-03-01',   -- 01-Mar-1979 = active
  brcod VARCHAR(5) NULL,
  UNIQUE KEY uq_agent (heaid, subheaid, agenum),
  UNIQUE KEY uq_agent_cus (heaid, subheaid, cusid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pigmy accounts (heaid 0) and RD accounts (heaid 1) have separate tables; agents share newpigage
CREATE TABLE IF NOT EXISTS newpigacc (
  id INT AUTO_INCREMENT PRIMARY KEY,
  heaid TINYINT NOT NULL DEFAULT 0,
  subheaid INT NOT NULL,
  agenum INT NOT NULL,
  accnum INT NOT NULL,
  cusid INT NOT NULL,
  macnam VARCHAR(30) NULL,
  accdat DATE NOT NULL,
  clodat DATE NOT NULL DEFAULT '1979-03-01',
  matdat DATE NULL,
  gurid INT NOT NULL DEFAULT 0,
  nom VARCHAR(100) NULL,
  rel VARCHAR(50) NULL,
  gurrel VARCHAR(50) NULL,
  loasta TINYINT NOT NULL DEFAULT 0,
  useidcre INT NULL,
  brcod VARCHAR(5) NULL,
  UNIQUE KEY uq_pig (heaid, subheaid, agenum, accnum),
  KEY (cusid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newrdacc (
  id INT AUTO_INCREMENT PRIMARY KEY,
  heaid TINYINT NOT NULL DEFAULT 1,
  subheaid INT NOT NULL,
  agenum INT NOT NULL,
  accnum INT NOT NULL,
  cusid INT NOT NULL,
  macnam VARCHAR(30) NULL,
  accdat DATE NOT NULL,
  clodat DATE NOT NULL DEFAULT '1979-03-01',
  matdat DATE NULL,
  matamo DECIMAL(14,2) NOT NULL DEFAULT 0,
  gurid INT NOT NULL DEFAULT 0,
  nom VARCHAR(100) NULL,
  rel VARCHAR(50) NULL,
  gurrel VARCHAR(50) NULL,
  loasta TINYINT NOT NULL DEFAULT 0,
  useidcre INT NULL,
  brcod VARCHAR(5) NULL,
  UNIQUE KEY uq_rd (heaid, subheaid, agenum, accnum),
  KEY (cusid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RD instalment detail; instyp 1 monthly, 2 quarterly, 3 half yearly, 4 yearly
CREATE TABLE IF NOT EXISTS rdinst (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subheaid INT NOT NULL,
  agenum INT NOT NULL,
  accnum INT NOT NULL,
  accdat DATE NOT NULL,
  insamo DECIMAL(14,2) NOT NULL DEFAULT 0,
  instyp TINYINT NOT NULL DEFAULT 0,
  intper DECIMAL(6,2) NOT NULL DEFAULT 0,
  noofins INT NOT NULL DEFAULT 0,
  offid INT NOT NULL DEFAULT 0,
  offper DECIMAL(6,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_rdinst (subheaid, agenum, accnum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- last agent number (agenum 0 row) and last account number for each agent
CREATE TABLE IF NOT EXISTS pigseq (
  heaid TINYINT NOT NULL,
  subheaid INT NOT NULL,
  agenum INT NOT NULL,
  last INT NOT NULL,
  PRIMARY KEY (heaid, subheaid, agenum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== Loans =====
-- typofloa: 0 term deposit loan, 1 CC deposit loan, 2 pigmy deposit loan, 3 RD deposit loan, 4 other loan
-- loaid = scheme_master.id (the deposit master or the loan master); loanum runs per typofloa + loaid
CREATE TABLE IF NOT EXISTS newloa (                  -- other loans (typofloa 4)
  id INT AUTO_INCREMENT PRIMARY KEY,
  typofloa TINYINT NOT NULL,
  loaid INT NOT NULL,
  loanum INT NOT NULL,
  cusid INT NOT NULL,
  sanloa DECIMAL(14,2) NOT NULL DEFAULT 0,
  instyp TINYINT NOT NULL DEFAULT 1,                 -- 1 monthly, 2 day wise
  dayas INT NOT NULL DEFAULT 1,                      -- "after every" days or months
  noofins INT NOT NULL DEFAULT 0,
  insamo DECIMAL(14,2) NOT NULL DEFAULT 0,
  gapins INT NOT NULL DEFAULT 0,
  cerno INT NOT NULL DEFAULT 1,
  clodat DATE NOT NULL DEFAULT '1979-03-01',         -- 01-Mar-1979 = loan open
  sta TINYINT NOT NULL DEFAULT 1,
  loadat DATE NOT NULL,
  paymode TINYINT NOT NULL DEFAULT 0,                -- 0 cash, 1 contra
  brcod VARCHAR(5) NULL,
  useidcre INT NULL,
  UNIQUE KEY uq_loa (typofloa, loaid, loanum, cerno),
  KEY (cusid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newloa_dep (              -- deposit loans (typofloa 0 to 3)
  id INT AUTO_INCREMENT PRIMARY KEY,
  typofloa TINYINT NOT NULL,
  loaid INT NOT NULL,
  loanum INT NOT NULL,
  cusid INT NOT NULL,
  sanloa DECIMAL(14,2) NOT NULL DEFAULT 0,
  cerno INT NOT NULL DEFAULT 1,
  clodat DATE NOT NULL DEFAULT '1979-03-01',
  sta TINYINT NOT NULL DEFAULT 1,
  ids VARCHAR(20) NULL,
  loadat DATE NOT NULL,
  paymode TINYINT NOT NULL DEFAULT 0,
  brcod VARCHAR(5) NULL,
  useidcre INT NULL,
  UNIQUE KEY uq_loadep (typofloa, loaid, loanum, cerno),
  KEY (cusid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newloa_dep_list (         -- deposits pledged to a deposit loan
  id INT AUTO_INCREMENT PRIMARY KEY,
  typofloa TINYINT NOT NULL,
  loaid INT NOT NULL,
  loanum INT NOT NULL,
  cerno INT NOT NULL DEFAULT 1,
  depnum INT NOT NULL,                               -- deposit account number
  depagenum INT NOT NULL DEFAULT 0,                  -- agent number for pigmy and RD, else 0
  depcerno INT NOT NULL DEFAULT 1,
  sta TINYINT NOT NULL DEFAULT 1,
  KEY (typofloa, loaid, loanum),
  KEY (typofloa, depnum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newloa_sur_dir (          -- surety rows have surordir 1 and surid = customer, director rows have surordir 0 and dirid = director.id
  id INT AUTO_INCREMENT PRIMARY KEY,
  typofloa TINYINT NOT NULL,
  loaid INT NOT NULL,
  loanum INT NOT NULL,
  surid INT NOT NULL DEFAULT 0,
  dirid INT NOT NULL DEFAULT 0,
  cerno INT NOT NULL DEFAULT 1,
  surordir TINYINT NOT NULL DEFAULT 1,
  KEY (typofloa, loaid, loanum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newloa_pledge (           -- pledge types chosen for a loan (ids 1 to 6 as in the loan master)
  id INT AUTO_INCREMENT PRIMARY KEY,
  typofloa TINYINT NOT NULL,
  loaid INT NOT NULL,
  loanum INT NOT NULL,
  cerno INT NOT NULL DEFAULT 1,
  pledgeid INT NOT NULL,
  KEY (typofloa, loaid, loanum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loaseq (
  typofloa TINYINT NOT NULL,
  loaid INT NOT NULL,
  last INT NOT NULL,
  PRIMARY KEY (typofloa, loaid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_otp (
  user_id INT NOT NULL PRIMARY KEY,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT NOT NULL DEFAULT 0,
  sent_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
