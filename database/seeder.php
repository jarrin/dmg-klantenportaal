<?php
// Enhanced Seeder for DMG Klantportaal
// Usage:
// php seeder.php --users=5000 --batch=500 --min-products=1 --max-products=5 --test
// Short form: php seeder.php 1000

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/config/Database.php';

// -------------------------
// Helpers
// -------------------------
function rand_item($arr) { return $arr[array_rand($arr)]; }
function random_string($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $s = '';
    for ($i = 0; $i < $length; $i++) $s .= $chars[random_int(0, strlen($chars)-1)];
    return $s;
}
function random_date_between($start, $end) {
    $ts = random_int(strtotime($start), strtotime($end));
    return date('Y-m-d', $ts);
}
function parse_cli_args($argv) {
    $out = [];
    foreach ($argv as $arg) {
        if (preg_match('/^--([a-z0-9_-]+)=(.*)$/i', $arg, $m)) {
            $out[$m[1]] = $m[2];
        }
    }
    return $out;
}

// -------------------------
// Data pools (expandable)
// -------------------------
$firstNames = ['Jan','Pieter','Klaas','Sanne','Emma','Julia','Lars','Hendrik','Marieke','Lisa','Tom','Jeroen','Iris','Bas','Jasmijn','Sophie','Daan','Noah','Olivia','Lucas','Femke','Ruben','Milan','Tessa','Rik'];
$lastNames = ['de Vries','Jansen','van Dijk','Bakker','Pieters','Smit','Visser','Meijer','Mulder','Brouwer','Vos','Hendriks','van den Berg','Dekker','Bos','Schouten'];
$cities = ['Amsterdam','Rotterdam','Utrecht','Eindhoven','Groningen','Maastricht','Leiden','Haarlem','Den Haag','Tilburg','Zwolle','Almere'];
$streets = ['Dorpsstraat','Hoofdstraat','Stationsweg','Kerkstraat','Schoolstraat','Markt','Langestraat','Breestraat','Parklaan','Oosterdok','Nieuweweg'];
$companies = ['Acme BV','Nova Solutions','WebWorks','Demo Company','Alpha Hosting','Beta IT','Gamma Services','Cloudify','NetSystems'];
$domains = ['example.net','demo.local','testdrive.dev','example.org','dmg-demo.nl','mydomain.nl','hosting.test'];
$productNames = ['Basis Hosting','Business Hosting','Premium Hosting','Domeinnaam','E-mailpakket','SLA Bronze','SLA Silver','SLA Gold','Managed DB','CDN Addon'];
$ticketSubjects = ['Website offline','E-mail problemen','Factuur vraag','SSL niet geldig','DNS configuratie','Snelheid probleem','Backups missen','Database timeouts'];
$ticketMessages = [
    'Ik kan mijn website niet bereiken sinds vanochtend.',
    'De e-mail wordt niet afgeleverd bij klanten.',
    'Is het mogelijk om extra schijfruimte toe te voegen?',
    'Ik heb een foutmelding bij inloggen.',
    'De factuur klopt niet, graag controleren.',
    'Hoe kan ik mijn DNS records updaten?'
];
$reasons = ['Gebrek aan gebruik','Verhuis naar andere provider','Te duur','Andere reden'];

// -------------------------
// CLI / config
// -------------------------
$cli = parse_cli_args($argv);
$numUsers = isset($cli['users']) ? (int)$cli['users'] : (isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : 2000);
if ($numUsers < 1) $numUsers = 2000;
$batchSize = isset($cli['batch']) ? (int)$cli['batch'] : 500;
$minProductsPerUser = isset($cli['min-products']) ? (int)$cli['min-products'] : 1;
$maxProductsPerUser = isset($cli['max-products']) ? (int)$cli['max-products'] : 5;
$minTicketsPerUser = isset($cli['min-tickets']) ? (int)$cli['min-tickets'] : 0;
$maxTicketsPerUser = isset($cli['max-tickets']) ? (int)$cli['max-tickets'] : 3;
$testMode = isset($cli['test']) ? true : false;
if ($testMode) {
    $numUsers = min(50, $numUsers);
    $batchSize = 50;
}

// -------------------------
// Persistence abstraction (DB or SQL file fallback)
// -------------------------
function rand_price() { return round(random_int(500, 20000) / 100, 2); }

// Persistor interface using closures-like methods implemented by classes below
class DbPersistor {
    private $db;
    private $stmts = [];
    public function __construct($db) {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // ensure product_types
        $stmt = $this->db->query("SELECT id FROM product_types");
        $productTypeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($productTypeIds)) {
            $this->db->exec("INSERT INTO product_types (name, description, default_duration_months) VALUES
                ('Hosting', 'Webhosting service', 12),
                ('Domeinnaam', 'Domain name registration', 12),
                ('E-mailpakket', 'Email hosting package', 12),
                ('SLA Contract', 'Service Level Agreement', 12),
                ('Managed DB', 'Managed database', 12)");
        }

        $this->stmts['user'] = $this->db->prepare("INSERT INTO users (email, password, first_name, last_name, company_name, address, postal_code, city, country, phone, role, created_at, last_login, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'customer', ?, ?, 1)");
        $this->stmts['product'] = $this->db->prepare("INSERT INTO products (user_id, product_type_id, name, description, domain_name, registration_date, expiry_date, duration_months, price, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $this->stmts['ticket'] = $this->db->prepare("INSERT INTO tickets (user_id, subject, status, priority, created_at) VALUES (?, ?, ?, ?, ?)");
        $this->stmts['ticket_msg'] = $this->db->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_staff_reply, created_at) VALUES (?, ?, ?, ?, ?)");
        $this->stmts['chat'] = $this->db->prepare("INSERT INTO chat_messages (user_id, message, is_staff_reply, created_at) VALUES (?, ?, ?, ?)");
        $this->stmts['payment'] = $this->db->prepare("INSERT INTO payment_preferences (user_id, payment_method, iban, account_holder_name, mandate_date, mandate_signature, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $this->stmts['request'] = $this->db->prepare("INSERT INTO product_requests (user_id, product_type_id, requested_name, requested_domain, additional_info, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
        $this->stmts['cancel'] = $this->db->prepare("INSERT INTO cancellation_requests (user_id, product_id, reason, status, created_at) VALUES (?, ?, ?, 'pending', ?)");
    }
    public function begin() { $this->db->beginTransaction(); }
    public function commit() { $this->db->commit(); }
    public function insertUser($data) {
        global $samplePasswordHash;
        $this->stmts['user']->execute([
            $data['email'],$samplePasswordHash,$data['first_name'],$data['last_name'],$data['company_name'],$data['address'],$data['postal_code'],$data['city'],$data['country'],$data['phone'],$data['created_at'],$data['last_login']
        ]);
        return $this->db->lastInsertId();
    }
    public function insertPayment($data) {
        $this->stmts['payment']->execute([$data['user_id'],$data['method'],$data['iban'],$data['account_name'],$data['mandate_date'],$data['mandate_sig'],$data['created_at']]);
    }
    public function insertProduct($data) {
        $this->stmts['product']->execute([$data['user_id'],$data['product_type_id'],$data['name'],$data['description'],$data['domain_name'],$data['registration_date'],$data['expiry_date'],$data['duration_months'],$data['price'],$data['status'],$data['created_at']]);
        return $this->db->lastInsertId();
    }
    public function insertCancellation($data) { $this->stmts['cancel']->execute([$data['user_id'],$data['product_id'],$data['reason'],$data['created_at']]); }
    public function insertRequest($data) { $this->stmts['request']->execute([$data['user_id'],$data['product_type_id'],$data['requested_name'],$data['requested_domain'],$data['additional_info'],$data['created_at']]); }
    public function insertTicket($data) { $this->stmts['ticket']->execute([$data['user_id'],$data['subject'],$data['status'],$data['priority'],$data['created_at']]); return $this->db->lastInsertId(); }
    public function insertTicketMessage($data) { $this->stmts['ticket_msg']->execute([$data['ticket_id'],$data['user_id'],$data['message'],$data['is_staff'],$data['created_at']]); }
    public function insertChat($data) { $this->stmts['chat']->execute([$data['user_id'],$data['message'],$data['is_staff'],$data['created_at']]); }
}

class SqlFilePersistor {
    private $fh;
    private $nextUser = 1000;
    private $nextProduct = 200000;
    private $nextTicket = 300000;
    public function __construct($path) {
        $this->fh = fopen($path, 'w');
        fwrite($this->fh, "-- Seeder SQL output generated by seeder.php\n-- Import this file into your MySQL/MariaDB instance.\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    }
    private function esc($v) {
        if (is_null($v)) return 'NULL';
        return "'" . str_replace("'","''", $v) . "'";
    }
    public function begin() {}
    public function commit() { fflush($this->fh); }
    public function insertUser($data) {
        $id = $this->nextUser++;
        $cols = ['id','email','password','first_name','last_name','company_name','address','postal_code','city','country','phone','role','created_at','last_login','active'];
        $vals = [$id, $data['email'], '"' . 'REDACTED_HASH' . '"', $data['first_name'], $data['last_name'], $data['company_name'], $data['address'], $data['postal_code'], $data['city'], $data['country'], $data['phone'], 'customer', $data['created_at'], $data['last_login'], 1];
        // build SQL
        $sql = "INSERT INTO users (" . implode(',', $cols) . ") VALUES (";
        $parts = [];
        foreach ($vals as $v) $parts[] = (is_null($v) ? 'NULL' : "'" . str_replace("'","''", $v) . "'");
        $sql .= implode(',', $parts) . ");\n";
        fwrite($this->fh, $sql);
        return $id;
    }
    public function insertPayment($data) {
        $sql = sprintf("INSERT INTO payment_preferences (user_id,payment_method,iban,account_holder_name,mandate_date,mandate_signature,created_at) VALUES (%d,%s,%s,%s,%s,%s,%s);\n",
            $data['user_id'], $this->esc($data['method']), $this->esc($data['iban']), $this->esc($data['account_name']), $this->esc($data['mandate_date']), $this->esc($data['mandate_sig']), $this->esc($data['created_at']));
        fwrite($this->fh, $sql);
    }
    public function insertProduct($data) {
        $id = $this->nextProduct++;
        $sql = sprintf("INSERT INTO products (id,user_id,product_type_id,name,description,domain_name,registration_date,expiry_date,duration_months,price,status,created_at) VALUES (%d,%d,%d,%s,%s,%s,%s,%s,%d,%.2f,%s,%s);\n",
            $id, $data['user_id'], $data['product_type_id'], $this->esc($data['name']), $this->esc($data['description']), $this->esc($data['domain_name']), $this->esc($data['registration_date']), $this->esc($data['expiry_date']), $data['duration_months'], $data['price'], $this->esc($data['status']), $this->esc($data['created_at']));
        fwrite($this->fh, $sql);
        return $id;
    }
    public function insertCancellation($data) { fwrite($this->fh, sprintf("INSERT INTO cancellation_requests (user_id,product_id,reason,status,created_at) VALUES (%d,%d,%s,'pending',%s);\n", $data['user_id'],$data['product_id'],$this->esc($data['reason']),$this->esc($data['created_at']))); }
    public function insertRequest($data) { fwrite($this->fh, sprintf("INSERT INTO product_requests (user_id,product_type_id,requested_name,requested_domain,additional_info,status,created_at) VALUES (%d,%d,%s,%s,%s,'pending',%s);\n", $data['user_id'],$data['product_type_id'],$this->esc($data['requested_name']),$this->esc($data['requested_domain']),$this->esc($data['additional_info']),$this->esc($data['created_at']))); }
    public function insertTicket($data) { $id = $this->nextTicket++; fwrite($this->fh, sprintf("INSERT INTO tickets (id,user_id,subject,status,priority,created_at) VALUES (%d,%d,%s,%s,%s,%s);\n", $id,$data['user_id'],$this->esc($data['subject']),$this->esc($data['status']),$this->esc($data['priority']),$this->esc($data['created_at']))); return $id; }
    public function insertTicketMessage($data) { fwrite($this->fh, sprintf("INSERT INTO ticket_messages (ticket_id,user_id,message,is_staff_reply,created_at) VALUES (%d,%d,%s,%d,%s);\n", $data['ticket_id'],$data['user_id'],$this->esc($data['message']),$data['is_staff'],$this->esc($data['created_at']))); }
    public function insertChat($data) { fwrite($this->fh, sprintf("INSERT INTO chat_messages (user_id,message,is_staff_reply,created_at) VALUES (%d,%s,%d,%s);\n", $data['user_id'],$this->esc($data['message']),$data['is_staff'],$this->esc($data['created_at']))); }
}

// Attempt to instantiate DB persistor; if it fails, fall back to SQL file persistor
$useDb = true;
$persist = null;
try {
    $db = Database::getInstance()->getConnection();
    $persist = new DbPersistor($db);
    echo "Connected to database, using DB persistor.\n";
} catch (Exception $e) {
    $useDb = false;
    $sqlPath = __DIR__ . '/seeder_output.sql';
    $persist = new SqlFilePersistor($sqlPath);
    echo "Database connection failed ({$e->getMessage()}).\n";
    echo "Falling back to SQL-only mode. SQL will be written to: {$sqlPath}\n";
    // Ensure product_types exist in SQL output and provide ids for seeding
    $defaultProductTypes = [
        ['id'=>1,'name'=>'Hosting','desc'=>'Webhosting service','dur'=>12],
        ['id'=>2,'name'=>'Domeinnaam','desc'=>'Domain name registration','dur'=>12],
        ['id'=>3,'name'=>'E-mailpakket','desc'=>'Email hosting package','dur'=>12],
        ['id'=>4,'name'=>'SLA Contract','desc'=>'Service Level Agreement','dur'=>12],
        ['id'=>5,'name'=>'Managed DB','desc'=>'Managed database','dur'=>12],
    ];
    $lines = "-- default product_types\n";
    foreach ($defaultProductTypes as $pt) {
        $lines .= sprintf("INSERT INTO product_types (id,name,description,default_duration_months) VALUES (%d,%s,%s,%d);\n", $pt['id'], "'".str_replace("'","''",$pt['name'])."'", "'".str_replace("'","''",$pt['desc'])."'", $pt['dur']);
    }
    file_put_contents($sqlPath, $lines, FILE_APPEND);
    $productTypeIds = array_column($defaultProductTypes, 'id');
}

// reuse one bcrypt hash for speed (kept for DB mode; SQL mode writes REDACTED_HASH)
$samplePasswordHash = password_hash('customer123', PASSWORD_BCRYPT, ['cost' => defined('HASH_COST') ? HASH_COST : 10]);

// Main seeding driver using the $persist object
$start = microtime(true);
$createdUsers = 0;
echo "Starting enhanced seeding: {$numUsers} users (batch={$batchSize})\n";

for ($u = 0; $u < $numUsers; $u++) {
    if ($u % $batchSize === 0) {
        if ($u > 0) $persist->commit();
        $persist->begin();
    }

    $first = rand_item($firstNames);
    $last = rand_item($lastNames);
    $localSuffix = $u + 1000;
    $email = strtolower(preg_replace('/[^a-z0-9]/', '', $first)) . '.' . strtolower(preg_replace('/[^a-z0-9]/', '', $last)) . '.' . $localSuffix . '@' . rand_item($domains);
    $company = (random_int(0, 5) === 0) ? rand_item($companies) : null;
    $address = random_int(1, 400) . ' ' . rand_item($streets);
    $postal = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT) . ' ' . chr(65 + random_int(0, 2)) . chr(65 + random_int(0, 2));
    $city = rand_item($cities);
    $phone = '+31' . random_int(600000000, 699999999);
    $createdAt = date('Y-m-d H:i:s', strtotime('-' . random_int(0, 900) . ' days'));
    $lastLogin = date('Y-m-d H:i:s', strtotime($createdAt . ' +' . random_int(0, 400) . ' days'));

    $userId = $persist->insertUser([
        'email'=>$email,'first_name'=>$first,'last_name'=>$last,'company_name'=>$company,'address'=>$address,'postal_code'=>$postal,'city'=>$city,'country'=>'Nederland','phone'=>$phone,'created_at'=>$createdAt,'last_login'=>$lastLogin
    ]);
    $createdUsers++;

    if (random_int(1, 100) <= 30) {
        $pm = (random_int(0, 100) < 55) ? 'invoice' : 'direct_debit';
        $iban = ($pm === 'direct_debit') ? ('NL' . random_int(10,99) . random_string(14)) : null;
        $accName = ($pm === 'direct_debit') ? ($first . ' ' . $last) : null;
        $mandateDate = ($pm === 'direct_debit') ? date('Y-m-d', strtotime('-' . random_int(0, 800) . ' days')) : null;
        $mandateSig = ($pm === 'direct_debit') ? ('/uploads/signatures/sign_' . $userId . '.png') : null;
        $persist->insertPayment(['user_id'=>$userId,'method'=>$pm,'iban'=>$iban,'account_name'=>$accName,'mandate_date'=>$mandateDate,'mandate_sig'=>$mandateSig,'created_at'=>$createdAt]);
    }

    $numProducts = random_int($minProductsPerUser, $maxProductsPerUser);
    for ($p = 0; $p < $numProducts; $p++) {
        $ptype = rand_item($productTypeIds);
        $pname = rand_item($productNames) . ' ' . strtoupper(random_string(3));
        $desc = $pname . ' - demo product';
        $domain = (random_int(0, 1) ? strtolower($first) . strtolower($last) . $userId . '.' . rand_item($domains) : null);
        $regDate = random_date_between('2022-01-01', '2025-06-01');
        $duration = rand(12, 48);
        $expiry = date('Y-m-d', strtotime($regDate . ' + ' . $duration . ' months'));
        $price = rand_price();
        $status = (random_int(1,100) <= 80) ? 'active' : (random_int(0,1) ? 'expired' : 'cancelled');
        $created = date('Y-m-d H:i:s', strtotime($regDate . ' +' . rand(0,30) . ' days'));
        $productId = $persist->insertProduct(['user_id'=>$userId,'product_type_id'=>$ptype,'name'=>$pname,'description'=>$desc,'domain_name'=>$domain,'registration_date'=>$regDate,'expiry_date'=>$expiry,'duration_months'=>$duration,'price'=>$price,'status'=>$status,'created_at'=>$created]);
        if ($status === 'cancelled' && random_int(1,100) <= 45) {
            $persist->insertCancellation(['user_id'=>$userId,'product_id'=>$productId,'reason'=>rand_item($reasons),'created_at'=>date('Y-m-d H:i:s', strtotime('-' . random_int(1, 500) . ' days'))]);
        }
    }

    if (random_int(1,100) <= 6) {
        $ptype = rand_item($productTypeIds);
        $reqName = 'Requested ' . strtoupper(random_string(4));
        $reqDomain = strtolower(random_string(6)) . '.' . rand_item(['nl','com','net']);
        $info = 'Automated request for demo seeding.';
        $persist->insertRequest(['user_id'=>$userId,'product_type_id'=>$ptype,'requested_name'=>$reqName,'requested_domain'=>$reqDomain,'additional_info'=>$info,'created_at'=>date('Y-m-d H:i:s', strtotime('-' . random_int(1, 400) . ' days'))]);
    }

    $numTickets = random_int($minTicketsPerUser, $maxTicketsPerUser);
    for ($t = 0; $t < $numTickets; $t++) {
        $subject = rand_item($ticketSubjects) . ' - ' . ucfirst(random_string(6));
        $status = rand_item(['new','in_progress','closed']);
        $priority = rand_item(['low','medium','high','urgent']);
        $tCreated = date('Y-m-d H:i:s', strtotime('-' . random_int(1, 1000) . ' days'));
        $ticketId = $persist->insertTicket(['user_id'=>$userId,'subject'=>$subject,'status'=>$status,'priority'=>$priority,'created_at'=>$tCreated]);
        $numMessages = random_int(1, 5);
        for ($m = 0; $m < $numMessages; $m++) {
            $isStaff = (random_int(1,100) <= 25) ? 1 : 0;
            $msg = rand_item($ticketMessages) . ' (seeded)';
            $msgCreated = date('Y-m-d H:i:s', strtotime($tCreated . ' +' . $m . ' hours'));
            $authorId = $isStaff ? 1 : $userId;
            $persist->insertTicketMessage(['ticket_id'=>$ticketId,'user_id'=>$authorId,'message'=>$msg,'is_staff'=>$isStaff,'created_at'=>$msgCreated]);
        }
    }

    $numChats = random_int(0, 3);
    for ($c = 0; $c < $numChats; $c++) {
        $isStaff = (random_int(1,100) <= 30) ? 1 : 0;
        $chatMsg = rand_item($ticketMessages) . ' (chat)';
        $chatCreated = date('Y-m-d H:i:s', strtotime('-' . random_int(1, 500) . ' days'));
        $persist->insertChat(['user_id'=>$userId,'message'=>$chatMsg,'is_staff'=>$isStaff,'created_at'=>$chatCreated]);
    }

    if ($u % max(1, (int)($numUsers/20)) === 0) {
        $percent = round(($u / $numUsers) * 100);
        echo "Seed progress: {$percent}% ({$u}/{$numUsers}) users inserted...\n";
    }
}

$persist->commit();
$elapsed = round(microtime(true) - $start, 2);
echo "Seeding complete. Users created: {$createdUsers}. Time: {$elapsed}s\n";

if (!$useDb) echo "SQL output saved to: {$sqlPath}\n";

echo "Notes:\n";
echo "- Default password for seeded users: customer123\n";
echo "- Admin/demo users from init.sql remain untouched if init was run.\n";
echo "- Adjust parameters via CLI: --users=, --batch=, --min-products=, --max-products=, --test\n";

// End
