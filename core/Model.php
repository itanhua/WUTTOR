<?php
/**
 * 模型基类 - 简易查询构造器
 */
class Model
{
    protected $table = '';
    protected $primaryKey = 'id';

    protected $wheres = [];
    protected $orders = [];
    protected $limit = null;
    protected $offset = null;
    protected $selects = ['*'];
    protected $joins = [];

    public function __construct()
    {
        if (empty($this->table)) {
            $this->table = strtolower(str_replace('Model', '', get_class($this)));
        }
    }

    /**
     * 给列名加反引号（防 MySQL 保留字 1064：char/image/order/key/text/values/option 等）。
     * 单 token 列名加反引号；含 . 空格 ( 等的表达式（如 users.id / COUNT(*) AS cnt / 子查询）原样返回——
     * 因为反引号会破坏点号/函数语法，调用方应自行确保这些复杂表达式的合法性。
     * 通配符 * 与 users.* / emoji.* 也原样返回（MySQL 不接受反引号包裹的 *）。
     */
    protected static function quoteIdent($name)
    {
        if (!is_string($name) || $name === '' || strpbrk($name, '. (') !== false) return $name;
        if ($name === '*') return '*';
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public static function table($table)
    {
        $instance = new static();
        $instance->table = $table;
        return $instance;
    }

    public function select($fields)
    {
        $this->selects = is_array($fields) ? $fields : func_get_args();
        return $this;
    }

    public function where($column, $operator = '=', $value = null)
    {
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['type' => 'AND', 'column' => $column, 'operator' => $operator, 'value' => $value];
        return $this;
    }
    public function orWhere($column, $operator = '=', $value = null)
    {
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['type' => 'OR', 'column' => $column, 'operator' => $operator, 'value' => $value];
        return $this;
    }

    /**
     * 裸 SQL 条件片段（带占位符 ?），用括号包裹后拼接。
     * 用于需要 OR 分组（如 (category_id = ? OR pin_scope = 2)）等 Model 平铺 where 无法表达的场景。
     */
    public function whereRaw($expression, $params = [], $type = 'AND')
    {
        $this->wheres[] = ['type' => $type, 'raw' => $expression, 'params' => (array)$params];
        return $this;
    }

    public function whereIn($column, $values)
    {
        $this->wheres[] = ['type' => 'AND', 'column' => $column, 'operator' => 'IN', 'value' => (array)$values];
        return $this;
    }

    public function whereLike($column, $value)
    {
        $this->wheres[] = ['type' => 'AND', 'column' => $column, 'operator' => 'LIKE', 'value' => $value];
        return $this;
    }

    public function whereNull($column)
    {
        $this->wheres[] = ['type' => 'AND', 'column' => $column, 'operator' => 'IS NULL', 'value' => null];
        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $this->orders[] = compact('column', 'direction');
        return $this;
    }

    /**
     * 原生 ORDER BY 表达式（如 COALESCE(...) DESC、FIELD()/INET_ATON() 等 ORM 难以表达的排序）
     * 注意：$expression 由调用方负责白名单，禁止直接拼接用户输入，仅用于内置排序逻辑
     */
    public function orderByRaw($expression)
    {
        $this->orders[] = ['raw' => true, 'expression' => $expression];
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = (int)$limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = (int)$offset;
        return $this;
    }

    public function join($table, $first, $operator, $second)
    {
        $this->joins[] = ['type' => 'INNER', 'table' => $table, 'first' => $first, 'operator' => $operator, 'second' => $second];
        return $this;
    }

    public function leftJoin($table, $first, $operator, $second)
    {
        $this->joins[] = ['type' => 'LEFT', 'table' => $table, 'first' => $first, 'operator' => $operator, 'second' => $second];
        return $this;
    }

    protected function buildWheres(&$params)
    {
        if (empty($this->wheres)) return '';
        $sql = ' WHERE ';
        $first = true;
        foreach ($this->wheres as $w) {
            if (!$first) {
                $sql .= ' ' . $w['type'] . ' ';
            }
            if (!empty($w['raw'])) {
                $sql .= '(' . $w['raw'] . ')';
                foreach ($w['params'] as $p) $params[] = $p;
            } elseif ($w['operator'] === 'IN') {
                $placeholders = implode(',', array_fill(0, count($w['value']), '?'));
                $sql .= self::quoteIdent($w['column']) . " IN ({$placeholders})";
                foreach ($w['value'] as $v) $params[] = $v;
            } elseif ($w['operator'] === 'IS NULL') {
                $sql .= self::quoteIdent($w['column']) . " IS NULL";
            } else {
                $sql .= self::quoteIdent($w['column']) . " {$w['operator']} ?";
                $params[] = $w['value'];
            }
            $first = false;
        }
        return $sql;
    }

    protected function buildSelect()
    {
        $sql = 'SELECT ' . implode(', ', array_map([self::class, 'quoteIdent'], $this->selects)) . ' FROM ' . $this->table;
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        $params = [];
        $sql .= $this->buildWheres($params);
        if (!empty($this->orders)) {
            $sql .= ' ORDER BY ' . implode(', ', array_map(function($o) {
                if (!empty($o['raw'])) return $o['expression'];
                return self::quoteIdent($o['column']) . " {$o['direction']}";
            }, $this->orders));
        }
        if ($this->limit !== null) $sql .= ' LIMIT ' . $this->limit;
        if ($this->offset !== null) $sql .= ' OFFSET ' . $this->offset;
        return [$sql, $params];
    }


    public function get()
    {
        list($sql, $params) = $this->buildSelect();
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function first()
    {
        $this->limit(1);
        $result = $this->get();
        return $result[0] ?? null;
    }

    public function count()
    {
        $originalSelects = $this->selects;
        $this->selects = ['COUNT(*) AS cnt'];
        list($sql, $params) = $this->buildSelect();
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        $this->selects = $originalSelects;
        return (int)($row['cnt'] ?? 0);
    }

    public function paginate($page = 1, $perPage = 15)
    {
        $page = max(1, (int)$page);
        $total = $this->count();
        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $data = $this->get();
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, ceil($total / $perPage)),
        ];
    }

    public function insert($data)
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        // 给列名加反引号：保护 MySQL 保留字（char/image/order/key/...）。
        // PDO/标准 SQL 接受反引号包裹的标识符，对所有合法列名都安全；变更前全工程曾因 char/image
        // 不加反引号被 1064 困扰，本方法修改后所有调用方（emoji_items.char 等）零成本受益。
        $quotedColumns = array_map(function ($c) { return '`' . str_replace('`', '``', $c) . '`'; }, $columns);
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(array_values($data));
        return Database::pdo()->lastInsertId();
    }

    public function update($data)
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            // 同 insert()：列名加反引号防 1064
            $sets[] = self::quoteIdent($col) . ' = ?';
            $params[] = $val;
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets);
        $sql .= $this->buildWheres($params);
        $stmt = Database::pdo()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete()
    {
        $sql = 'DELETE FROM ' . $this->table;
        $params = [];
        $sql .= $this->buildWheres($params);
        $stmt = Database::pdo()->prepare($sql);
        return $stmt->execute($params);
    }

    public function find($id)
    {
        return $this->where($this->primaryKey, $id)->first();
    }

    public static function query($sql, $params = [])
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function execute($sql, $params = [])
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function scalar($sql, $params = [])
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? reset($row) : null;
    }

    /**
     * ── 事务支持 ──
     * Model 本身是查询构造器，但很多 controller 需要「多条写操作要么全成功要么全回滚」，
     * 这里包装 PDO 的 beginTransaction/commit/rollBack。
     * 调用方一律用驼峰 rollBack()（与 PDO 原生方法同名），不要写小写，
     * 因为 PHP 方法名不区分大小写，再写个 rollback() 会被判为 redeclare 而整站 500。
     */
    public static function beginTransaction()
    {
        Database::pdo()->beginTransaction();
    }

    public static function commit()
    {
        Database::pdo()->commit();
    }

    public static function rollBack()
    {
        Database::pdo()->rollBack();
    }

    /**
     * 顶层事务糖：闭包内执行任意写操作，若抛异常自动回滚。
     */
    public static function transaction(callable $fn)
    {
        self::beginTransaction();
        try {
            $res = $fn();
            self::commit();
            return $res;
        } catch (\Throwable $e) {
            if (Database::pdo()->inTransaction()) self::rollBack();
            throw $e;
        }
    }
}
