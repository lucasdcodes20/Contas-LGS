<?php
/**
 * Camada de Banco de Dados - Estendida: Paginação, DateRange, Usuários, Recorrentes (Dual-Engine)
 */
require_once __DIR__ . '/config.php';

// --- INTERFACES ---
interface TransactionRepository {
    public function getAll(array $filters = []): array;
    public function getAllPaginated(array $filters = [], int $page = 1, int $perPage = 20): array;
    public function getById(int $id): ?array;
    public function save(array $transaction): array;
    public function delete(int $id): bool;
    public function getStats(array $filters = []): array;
}
interface UserRepository {
    public function getAll(): array;
    public function create(array $user): bool;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function getByUsername(string $username): ?array;
    public function getById(int $id): ?array;
    public function updatePassword(int $id, string $newHashedPassword): bool;
}
interface LinkRepository {
    public function getAll(): array;
    public function save(array $link): array;
    public function delete(int $id): bool;
}

// --- IMPLEMENTAÇÃO JSON ---

class JsonUserRepository implements UserRepository {
    private string $filePath;
    public function __construct() {
        $this->filePath = JSON_DB_DIR . '/users.json';
        $this->ensureFileExists();
    }
    private function ensureFileExists(): void {
        if (!file_exists($this->filePath)) {
            $admin = ['id'=>1,'name'=>'Administrador LGS','email'=>'admin@lgs.com','username'=>'admin',
                      'password'=>password_hash('adminLGS2026',PASSWORD_DEFAULT),'role'=>'admin','created_at'=>date('Y-m-d H:i:s')];
            file_put_contents($this->filePath, json_encode([$admin], JSON_PRETTY_PRINT));
            chmod($this->filePath, 0664);
        }
    }
    private function loadData(): array {
        if (!file_exists($this->filePath)) return [];
        return json_decode(file_get_contents($this->filePath), true) ?: [];
    }
    private function saveData(array $users): bool {
        return file_put_contents($this->filePath, json_encode(array_values($users), JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
    public function getAll(): array {
        return array_map(function($u){ unset($u['password']); return $u; }, $this->loadData());
    }
    public function create(array $user): bool {
        $users = $this->loadData();
        foreach ($users as $u) { if ($u['username'] === $user['username']) return false; }
        $maxId = 0;
        foreach ($users as $u) { if ((int)$u['id'] > $maxId) $maxId = (int)$u['id']; }
        $users[] = ['id'=>$maxId+1,'name'=>htmlspecialchars(trim($user['name']),ENT_QUOTES,'UTF-8'),
                    'email'=>htmlspecialchars(trim($user['email']),ENT_QUOTES,'UTF-8'),
                    'username'=>htmlspecialchars(trim($user['username']),ENT_QUOTES,'UTF-8'),
                    'password'=>password_hash($user['password'],PASSWORD_DEFAULT),
                    'role'=>$user['role']??'user','created_at'=>date('Y-m-d H:i:s')];
        return $this->saveData($users);
    }
    public function update(int $id, array $data): bool {
        $users = $this->loadData();
        foreach ($users as $key => $u) {
            if ((int)$u['id'] === $id) {
                if (isset($data['name']))  $users[$key]['name']  = htmlspecialchars(trim($data['name']),ENT_QUOTES,'UTF-8');
                if (isset($data['email'])) $users[$key]['email'] = htmlspecialchars(trim($data['email']),ENT_QUOTES,'UTF-8');
                if (isset($data['role']))  $users[$key]['role']  = $data['role'];
                return $this->saveData($users);
            }
        }
        return false;
    }
    public function delete(int $id): bool {
        $users = $this->loadData();
        $filtered = array_filter($users, fn($u) => (int)$u['id'] !== $id);
        if (count($filtered) === count($users)) return false;
        return $this->saveData($filtered);
    }
    public function getByUsername(string $username): ?array {
        foreach ($this->loadData() as $u) { if ($u['username'] === $username) return $u; }
        return null;
    }
    public function getById(int $id): ?array {
        foreach ($this->loadData() as $u) { if ((int)$u['id'] === $id) return $u; }
        return null;
    }
    public function updatePassword(int $id, string $newHashedPassword): bool {
        $users = $this->loadData();
        foreach ($users as $key => $u) {
            if ((int)$u['id'] === $id) { $users[$key]['password'] = $newHashedPassword; return $this->saveData($users); }
        }
        return false;
    }
}

class JsonLinkRepository implements LinkRepository {
    private string $filePath;
    public function __construct() { $this->filePath = JSON_DB_DIR . '/links.json'; $this->ensureFileExists(); }
    private function ensureFileExists(): void {
        if (!file_exists($this->filePath)) { file_put_contents($this->filePath, json_encode([], JSON_PRETTY_PRINT)); chmod($this->filePath, 0664); }
    }
    private function loadData(): array {
        if (!file_exists($this->filePath)) return [];
        return json_decode(file_get_contents($this->filePath), true) ?: [];
    }
    public function getAll(): array { return $this->loadData(); }
    public function save(array $link): array {
        $links = $this->loadData();
        $item = ['id'=>isset($link['id'])?(int)$link['id']:null,
                 'name'=>htmlspecialchars(trim($link['name']),ENT_QUOTES,'UTF-8'),
                 'url'=>htmlspecialchars(trim($link['url']),ENT_QUOTES,'UTF-8'),
                 'category'=>htmlspecialchars(trim($link['category']),ENT_QUOTES,'UTF-8'),
                 'created_at'=>$link['created_at']??date('Y-m-d H:i:s')];
        if ($item['id'] === null) {
            $maxId = 0;
            foreach ($links as $l) { if ((int)$l['id'] > $maxId) $maxId = (int)$l['id']; }
            $item['id'] = $maxId + 1; $links[] = $item;
        } else {
            foreach ($links as $key => $l) { if ((int)$l['id'] === $item['id']) { $links[$key] = $item; break; } }
        }
        file_put_contents($this->filePath, json_encode($links, JSON_PRETTY_PRINT), LOCK_EX);
        return $item;
    }
    public function delete(int $id): bool {
        $links = $this->loadData();
        $filtered = array_filter($links, fn($l) => (int)$l['id'] !== $id);
        if (count($filtered) === count($links)) return false;
        return file_put_contents($this->filePath, json_encode(array_values($filtered), JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}

class JsonTransactionRepository implements TransactionRepository {
    private string $filePath;
    public function __construct(string $filePath) { $this->filePath = $filePath; $this->ensureFileExists(); }
    private function ensureFileExists(): void {
        if (!file_exists($this->filePath)) { file_put_contents($this->filePath, json_encode([], JSON_PRETTY_PRINT)); chmod($this->filePath, 0664); }
    }
    private function loadData(): array {
        if (!file_exists($this->filePath)) return [];
        $data = json_decode(file_get_contents($this->filePath), true);
        return is_array($data) ? $data : [];
    }
    private function saveData(array $data): bool {
        return file_put_contents($this->filePath, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }
    private function matchesFilters(array $t, array $filters): bool {
        if (!empty($filters['search'])) {
            $s = mb_strtolower($filters['search'],'UTF-8');
            if (strpos(mb_strtolower($t['description'],'UTF-8'),$s)===false && strpos(mb_strtolower($t['category'],'UTF-8'),$s)===false) return false;
        }
        if (!empty($filters['type'])       && $t['type']     !== $filters['type'])     return false;
        if (!empty($filters['category'])   && $t['category'] !== $filters['category']) return false;
        if (!empty($filters['status'])     && $t['status']   !== $filters['status'])   return false;
        if (!empty($filters['month_year']) && substr($t['date'],0,7) !== $filters['month_year']) return false;
        if (!empty($filters['date_from'])  && $t['date'] < $filters['date_from']) return false;
        if (!empty($filters['date_to'])    && $t['date'] > $filters['date_to'])   return false;
        return true;
    }
    public function getAll(array $filters = []): array {
        $transactions = $this->loadData();
        usort($transactions, fn($a,$b) => $a['date']===$b['date'] ? $b['id']<=>$a['id'] : strcmp($b['date'],$a['date']));
        return array_values(array_filter($transactions, fn($t) => $this->matchesFilters($t,$filters)));
    }
    public function getAllPaginated(array $filters = [], int $page = 1, int $perPage = 20): array {
        $all = $this->getAll($filters); $total = count($all);
        return ['items'=>array_slice($all,($page-1)*$perPage,$perPage),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'total_pages'=>(int)ceil($total/$perPage)];
    }
    public function getById(int $id): ?array {
        foreach ($this->loadData() as $t) { if ((int)$t['id'] === $id) return $t; }
        return null;
    }
    public function save(array $transaction): array {
        $transactions = $this->loadData();
        $item = [
            'id'           => isset($transaction['id']) ? (int)$transaction['id'] : null,
            'description'  => htmlspecialchars(trim($transaction['description']),ENT_QUOTES,'UTF-8'),
            'amount'       => (float)$transaction['amount'],
            'date'         => preg_match('/^\d{4}-\d{2}-\d{2}$/',$transaction['date']) ? $transaction['date'] : date('Y-m-d'),
            'category'     => htmlspecialchars(trim($transaction['category']),ENT_QUOTES,'UTF-8'),
            'type'         => in_array($transaction['type'],['Receita','Despesa']) ? $transaction['type'] : 'Despesa',
            'status'       => in_array($transaction['status'],['Pago','Pendente']) ? $transaction['status'] : 'Pendente',
            'notes'        => isset($transaction['notes']) ? htmlspecialchars(trim($transaction['notes']),ENT_QUOTES,'UTF-8') : '',
            'recurring'    => isset($transaction['recurring']) ? (bool)$transaction['recurring'] : false,
            'recurring_day'=> isset($transaction['recurring_day']) ? (int)$transaction['recurring_day'] : null,
            'created_at'   => $transaction['created_at'] ?? date('Y-m-d H:i:s')
        ];
        if ($item['id'] === null) {
            $maxId = 0;
            foreach ($transactions as $t) { if ((int)$t['id'] > $maxId) $maxId = (int)$t['id']; }
            $item['id'] = $maxId + 1; $transactions[] = $item;
        } else {
            $updated = false;
            foreach ($transactions as $key => $t) {
                if ((int)$t['id'] === $item['id']) { $item['created_at']=$t['created_at']; $transactions[$key]=$item; $updated=true; break; }
            }
            if (!$updated) $transactions[] = $item;
        }
        $this->saveData($transactions);
        return $item;
    }
    public function delete(int $id): bool {
        $transactions = $this->loadData();
        $filtered = array_filter($transactions, fn($t) => (int)$t['id'] !== $id);
        if (count($filtered) === count($transactions)) return false;
        return $this->saveData($filtered);
    }
    public function getStats(array $filters = []): array {
        $transactions = $this->getAll($filters);
        $ti=$te=$pi=$pe=$pde=0.0; $cb=[];
        foreach ($transactions as $t) {
            $a=(float)$t['amount'];
            if ($t['type']==='Receita') { $ti+=$a; if($t['status']==='Pendente') $pi+=$a; }
            else { $te+=$a; if($t['status']==='Pago') $pde+=$a; else $pe+=$a; $cb[$t['category']]=($cb[$t['category']]??0.0)+$a; }
        }
        arsort($cb);
        return ['total_income'=>$ti,'total_expense'=>$te,'balance'=>($ti-$pi)-$pde,'paid_income'=>$ti-$pi,'pending_income'=>$pi,'paid_expense'=>$pde,'pending_expense'=>$pe,'category_breakdown'=>$cb];
    }
}

// --- IMPLEMENTAÇÃO MYSQL ---

class MySqlUserRepository implements UserRepository {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo=$pdo; $this->ensureTableExists(); }
    private function ensureTableExists(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `users` (`id` INT AUTO_INCREMENT PRIMARY KEY,`name` VARCHAR(100) NOT NULL,`email` VARCHAR(100) NOT NULL,`username` VARCHAR(50) NOT NULL UNIQUE,`password` VARCHAR(255) NOT NULL,`role` VARCHAR(50) DEFAULT 'user',`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $stmt=$this->pdo->query("SELECT COUNT(*) FROM `users`");
        if ((int)$stmt->fetchColumn()===0) {
            $s=$this->pdo->prepare("INSERT INTO `users` (`name`,`email`,`username`,`password`,`role`) VALUES(:n,:e,:u,:p,'admin')");
            $s->execute(['n'=>'Administrador LGS','e'=>'admin@lgs.com','u'=>'admin','p'=>password_hash('adminLGS2026',PASSWORD_DEFAULT)]);
        }
    }
    public function getAll(): array {
        return $this->pdo->query("SELECT `id`,`name`,`email`,`username`,`role`,`created_at` FROM `users` ORDER BY `id` ASC")->fetchAll();
    }
    public function create(array $user): bool {
        try {
            $stmt=$this->pdo->prepare("INSERT INTO `users` (`name`,`email`,`username`,`password`,`role`) VALUES(:name,:email,:username,:password,:role)");
            return $stmt->execute(['name'=>htmlspecialchars(trim($user['name']),ENT_QUOTES,'UTF-8'),'email'=>htmlspecialchars(trim($user['email']),ENT_QUOTES,'UTF-8'),'username'=>htmlspecialchars(trim($user['username']),ENT_QUOTES,'UTF-8'),'password'=>password_hash($user['password'],PASSWORD_DEFAULT),'role'=>$user['role']??'user']);
        } catch(PDOException $e) { return false; }
    }
    public function update(int $id, array $data): bool {
        $parts=[]; $params=['id'=>$id];
        if(isset($data['name']))  { $parts[]='`name`=:name';   $params['name'] =htmlspecialchars(trim($data['name']),ENT_QUOTES,'UTF-8'); }
        if(isset($data['email'])) { $parts[]='`email`=:email'; $params['email']=htmlspecialchars(trim($data['email']),ENT_QUOTES,'UTF-8'); }
        if(isset($data['role']))  { $parts[]='`role`=:role';   $params['role'] =$data['role']; }
        if(empty($parts)) return false;
        $stmt=$this->pdo->prepare("UPDATE `users` SET ".implode(',',$parts)." WHERE `id`=:id");
        $stmt->execute($params); return $stmt->rowCount()>0;
    }
    public function delete(int $id): bool {
        $stmt=$this->pdo->prepare("DELETE FROM `users` WHERE `id`=:id"); $stmt->execute(['id'=>$id]); return $stmt->rowCount()>0;
    }
    public function getByUsername(string $username): ?array {
        $stmt=$this->pdo->prepare("SELECT * FROM `users` WHERE `username`=:u"); $stmt->execute(['u'=>$username]); return $stmt->fetch()?:null;
    }
    public function getById(int $id): ?array {
        $stmt=$this->pdo->prepare("SELECT * FROM `users` WHERE `id`=:id"); $stmt->execute(['id'=>$id]); return $stmt->fetch()?:null;
    }
    public function updatePassword(int $id, string $newHashedPassword): bool {
        $stmt=$this->pdo->prepare("UPDATE `users` SET `password`=:pw WHERE `id`=:id"); $stmt->execute(['pw'=>$newHashedPassword,'id'=>$id]); return $stmt->rowCount()>0;
    }
}

class MySqlLinkRepository implements LinkRepository {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo=$pdo; $this->ensureTableExists(); }
    private function ensureTableExists(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `useful_links` (`id` INT AUTO_INCREMENT PRIMARY KEY,`name` VARCHAR(255) NOT NULL,`url` VARCHAR(500) NOT NULL,`category` VARCHAR(100) NOT NULL,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
    public function getAll(): array {
        $stmt=$this->pdo->query("SELECT * FROM `useful_links` ORDER BY `name` ASC");
        return array_map(fn($r)=>array_merge($r,['id'=>(int)$r['id']]),$stmt->fetchAll());
    }
    public function save(array $link): array {
        $id=isset($link['id'])?(int)$link['id']:null;
        $name=htmlspecialchars(trim($link['name']),ENT_QUOTES,'UTF-8'); $url=htmlspecialchars(trim($link['url']),ENT_QUOTES,'UTF-8'); $cat=htmlspecialchars(trim($link['category']),ENT_QUOTES,'UTF-8');
        if ($id===null) { $stmt=$this->pdo->prepare("INSERT INTO `useful_links` (`name`,`url`,`category`) VALUES(:n,:u,:c)"); $stmt->execute(['n'=>$name,'u'=>$url,'c'=>$cat]); $id=(int)$this->pdo->lastInsertId(); }
        else { $stmt=$this->pdo->prepare("UPDATE `useful_links` SET `name`=:n,`url`=:u,`category`=:c WHERE `id`=:id"); $stmt->execute(['n'=>$name,'u'=>$url,'c'=>$cat,'id'=>$id]); }
        $s=$this->pdo->prepare("SELECT * FROM `useful_links` WHERE `id`=:id"); $s->execute(['id'=>$id]); $row=$s->fetch(); $row['id']=(int)$row['id']; return $row;
    }
    public function delete(int $id): bool {
        $stmt=$this->pdo->prepare("DELETE FROM `useful_links` WHERE `id`=:id"); $stmt->execute(['id'=>$id]); return $stmt->rowCount()>0;
    }
}

class MySqlTransactionRepository implements TransactionRepository {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo=$pdo; $this->ensureTableExists(); }
    private function ensureTableExists(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `transactions` (`id` INT AUTO_INCREMENT PRIMARY KEY,`description` VARCHAR(255) NOT NULL,`amount` DECIMAL(10,2) NOT NULL,`date` DATE NOT NULL,`category` VARCHAR(100) NOT NULL,`type` ENUM('Receita','Despesa') NOT NULL,`status` ENUM('Pago','Pendente') NOT NULL,`notes` TEXT NULL,`recurring` TINYINT(1) DEFAULT 0,`recurring_day` TINYINT NULL,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_date(`date`),INDEX idx_type(`type`),INDEX idx_category(`category`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        try { $this->pdo->exec("ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `recurring` TINYINT(1) DEFAULT 0"); $this->pdo->exec("ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `recurring_day` TINYINT NULL"); } catch(PDOException $e) {}
    }
    private function buildWhere(array $filters): array {
        $sql=" WHERE 1=1"; $p=[];
        if(!empty($filters['search'])) { $sql.=" AND (`description` LIKE :s OR `category` LIKE :s2)"; $p['s']='%'.$filters['search'].'%'; $p['s2']='%'.$filters['search'].'%'; }
        if(!empty($filters['type']))       { $sql.=" AND `type`=:type";                                 $p['type']      =$filters['type']; }
        if(!empty($filters['category']))   { $sql.=" AND `category`=:category";                         $p['category']  =$filters['category']; }
        if(!empty($filters['status']))     { $sql.=" AND `status`=:status";                             $p['status']    =$filters['status']; }
        if(!empty($filters['month_year'])) { $sql.=" AND DATE_FORMAT(`date`,'%Y-%m')=:my";              $p['my']        =$filters['month_year']; }
        if(!empty($filters['date_from']))  { $sql.=" AND `date`>=:df";                                  $p['df']        =$filters['date_from']; }
        if(!empty($filters['date_to']))    { $sql.=" AND `date`<=:dt";                                  $p['dt']        =$filters['date_to']; }
        return ['sql'=>$sql,'params'=>$p];
    }
    private function nr(array $r): array {
        $r['id']=(int)$r['id']; $r['amount']=(float)$r['amount'];
        $r['recurring']=(bool)($r['recurring']??false); $r['recurring_day']=isset($r['recurring_day'])?(int)$r['recurring_day']:null;
        return $r;
    }
    public function getAll(array $filters=[]): array {
        ['sql'=>$w,'params'=>$p]=$this->buildWhere($filters);
        $stmt=$this->pdo->prepare("SELECT * FROM `transactions`".$w." ORDER BY `date` DESC,`id` DESC");
        $stmt->execute($p); return array_map([$this,'nr'],$stmt->fetchAll());
    }
    public function getAllPaginated(array $filters=[], int $page=1, int $perPage=20): array {
        ['sql'=>$w,'params'=>$p]=$this->buildWhere($filters);
        $cs=$this->pdo->prepare("SELECT COUNT(*) FROM `transactions`".$w); $cs->execute($p); $total=(int)$cs->fetchColumn();
        $offset=($page-1)*$perPage;
        $stmt=$this->pdo->prepare("SELECT * FROM `transactions`".$w." ORDER BY `date` DESC,`id` DESC LIMIT :lim OFFSET :off");
        foreach($p as $k=>$v) $stmt->bindValue(':'.$k,$v);
        $stmt->bindValue(':lim',$perPage,PDO::PARAM_INT); $stmt->bindValue(':off',$offset,PDO::PARAM_INT);
        $stmt->execute();
        return ['items'=>array_map([$this,'nr'],$stmt->fetchAll()),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'total_pages'=>(int)ceil($total/$perPage)];
    }
    public function getById(int $id): ?array {
        $stmt=$this->pdo->prepare("SELECT * FROM `transactions` WHERE `id`=:id"); $stmt->execute(['id'=>$id]); $r=$stmt->fetch(); return $r?$this->nr($r):null;
    }
    public function save(array $t): array {
        $id=isset($t['id'])?(int)$t['id']:null;
        $desc=htmlspecialchars(trim($t['description']),ENT_QUOTES,'UTF-8'); $amt=(float)$t['amount'];
        $date=preg_match('/^\d{4}-\d{2}-\d{2}$/',$t['date'])?$t['date']:date('Y-m-d');
        $cat=htmlspecialchars(trim($t['category']),ENT_QUOTES,'UTF-8');
        $type=in_array($t['type'],['Receita','Despesa'])?$t['type']:'Despesa';
        $status=in_array($t['status'],['Pago','Pendente'])?$t['status']:'Pendente';
        $notes=isset($t['notes'])?htmlspecialchars(trim($t['notes']),ENT_QUOTES,'UTF-8'):'';
        $rec=isset($t['recurring'])?(int)(bool)$t['recurring']:0;
        $rday=($rec&&isset($t['recurring_day']))?(int)$t['recurring_day']:null;
        if($id===null){
            $stmt=$this->pdo->prepare("INSERT INTO `transactions` (`description`,`amount`,`date`,`category`,`type`,`status`,`notes`,`recurring`,`recurring_day`) VALUES(:d,:a,:dt,:c,:ty,:st,:n,:r,:rd)");
            $stmt->execute(['d'=>$desc,'a'=>$amt,'dt'=>$date,'c'=>$cat,'ty'=>$type,'st'=>$status,'n'=>$notes,'r'=>$rec,'rd'=>$rday]); $id=(int)$this->pdo->lastInsertId();
        } else {
            $stmt=$this->pdo->prepare("UPDATE `transactions` SET `description`=:d,`amount`=:a,`date`=:dt,`category`=:c,`type`=:ty,`status`=:st,`notes`=:n,`recurring`=:r,`recurring_day`=:rd WHERE `id`=:id");
            $stmt->execute(['id'=>$id,'d'=>$desc,'a'=>$amt,'dt'=>$date,'c'=>$cat,'ty'=>$type,'st'=>$status,'n'=>$notes,'r'=>$rec,'rd'=>$rday]);
        }
        return $this->getById($id);
    }
    public function delete(int $id): bool {
        $stmt=$this->pdo->prepare("DELETE FROM `transactions` WHERE `id`=:id"); $stmt->execute(['id'=>$id]); return $stmt->rowCount()>0;
    }
    public function getStats(array $filters=[]): array {
        $transactions=$this->getAll($filters);
        $ti=$te=$pi=$pe=$pde=0.0; $cb=[];
        foreach($transactions as $t){ $a=(float)$t['amount']; if($t['type']==='Receita'){$ti+=$a;if($t['status']==='Pendente')$pi+=$a;}else{$te+=$a;if($t['status']==='Pago')$pde+=$a;else $pe+=$a;$cb[$t['category']]=($cb[$t['category']]??0.0)+$a;} }
        arsort($cb);
        return ['total_income'=>$ti,'total_expense'=>$te,'balance'=>($ti-$pi)-$pde,'paid_income'=>$ti-$pi,'pending_income'=>$pi,'paid_expense'=>$pde,'pending_expense'=>$pe,'category_breakdown'=>$cb];
    }
}

// --- DATABASE FACTORY ---
class Database {
    private static ?PDO $pdo=null;
    private static ?TransactionRepository $transactionRepo=null;
    private static ?UserRepository $userRepo=null;
    private static ?LinkRepository $linkRepo=null;

    private static function getPdo(): PDO {
        if(self::$pdo!==null) return self::$pdo;
        $dsn="mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
        try {
            self::$pdo=new PDO($dsn,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        } catch(PDOException $e) {
            if($e->getCode()==1049){
                $tp=new PDO("mysql:host=".DB_HOST.";port=".DB_PORT.";charset=utf8mb4",DB_USER,DB_PASS);
                $tp->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$pdo=new PDO($dsn,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
            } else { throw new Exception("Conexão ao MariaDB falhou: ".$e->getMessage()); }
        }
        return self::$pdo;
    }
    public static function getRepository(): TransactionRepository {
        if(self::$transactionRepo!==null) return self::$transactionRepo;
        self::$transactionRepo=DB_ENGINE==='mysql'?new MySqlTransactionRepository(self::getPdo()):new JsonTransactionRepository(JSON_DB_FILE);
        return self::$transactionRepo;
    }
    public static function getUserRepository(): UserRepository {
        if(self::$userRepo!==null) return self::$userRepo;
        self::$userRepo=DB_ENGINE==='mysql'?new MySqlUserRepository(self::getPdo()):new JsonUserRepository();
        return self::$userRepo;
    }
    public static function getLinkRepository(): LinkRepository {
        if(self::$linkRepo!==null) return self::$linkRepo;
        self::$linkRepo=DB_ENGINE==='mysql'?new MySqlLinkRepository(self::getPdo()):new JsonLinkRepository();
        return self::$linkRepo;
    }
}
