<?php
declare(strict_types=1);

namespace Paradox;

class Paradox
{
    /**
     * 文件类型
     */
    public const FILE_TYPE_DB_FILE_INDEXED = 0;
    public const FILE_TYPE_PX_FILE = 1;
    public const FILE_TYPE_DB_FILE_NOT_INDEXED = 2;
    public const FILE_TYPE_XNN_FILE_NON_INC = 3;
    public const FILE_TYPE_YNN_FILE = 4;
    public const FILE_TYPE_XNN_FILE_INC = 5;
    public const FILE_TYPE_XGN_FILE_NON_INC = 6;
    public const FILE_TYPE_YGN_FILE = 7;
    public const FILE_TYPE_XGN_FILE_INC = 8;
    public const FILE_TYPES = [
        self::FILE_TYPE_DB_FILE_INDEXED     =>  'DbFileIndexed',
        self::FILE_TYPE_PX_FILE             =>  'PxFile',
        self::FILE_TYPE_DB_FILE_NOT_INDEXED =>  'DbFileNotIndexed',
        self::FILE_TYPE_XNN_FILE_NON_INC    =>  'XnnFileNonInc',
        self::FILE_TYPE_YNN_FILE            =>  'YnnFile',
        self::FILE_TYPE_XNN_FILE_INC        =>  'XnnFileInc',
        self::FILE_TYPE_XGN_FILE_NON_INC    =>  'XgnFileNonInc',
        self::FILE_TYPE_YGN_FILE            =>  'YgnFile',
        self::FILE_TYPE_XGN_FILE_INC        =>  'XgnFileInc',
    ];

    /**
     * 字段类型
     */
    public const FIELD_TYPE_ALPHA = 0x01;
    public const FIELD_TYPE_DATE = 0x02;
    public const FIELD_TYPE_SHORT = 0x03;
    public const FIELD_TYPE_LONG = 0x04;
    public const FIELD_TYPE_CURRENCY = 0x05;
    public const FIELD_TYPE_NUMBER = 0x06;
    public const FIELD_TYPE_LOGICAL = 0x09;
    public const FIELD_TYPE_MEMOBLOB = 0x0C;
    public const FIELD_TYPE_BLOB = 0x0D;
    public const FIELD_TYPE_FMTMEMOBLOB = 0x0E;
    public const FIELD_TYPE_OLE = 0x0F;
    public const FIELD_TYPE_GRAPHIC = 0x10;
    public const FIELD_TYPE_TIME = 0x14;
    public const FIELD_TYPE_TIMESTAMP = 0x15;
    public const FIELD_TYPE_AUTOINC = 0x16;
    public const FIELD_TYPE_BCD = 0x17;
    public const FIELD_TYPE_BYTES = 0x18;
    public const FIELD_TYPES = [
        self::FIELD_TYPE_ALPHA          =>  'Alpha',
        self::FIELD_TYPE_DATE           =>  'Date',
        self::FIELD_TYPE_SHORT          =>  'Short',
        self::FIELD_TYPE_LONG           =>  'Long',
        self::FIELD_TYPE_CURRENCY       =>  'Currency',
        self::FIELD_TYPE_NUMBER         =>  'Number',
        self::FIELD_TYPE_LOGICAL        =>  'Logical',
        self::FIELD_TYPE_MEMOBLOB       =>  'Memoblob',
        self::FIELD_TYPE_BLOB           =>  'Blob',
        self::FIELD_TYPE_FMTMEMOBLOB    =>  'Fmtmemoblob',
        self::FIELD_TYPE_OLE            =>  'Ole',
        self::FIELD_TYPE_GRAPHIC        =>  'Graphic',
        self::FIELD_TYPE_TIME           =>  'Time',
        self::FIELD_TYPE_TIMESTAMP      =>  'Timestamp',
        self::FIELD_TYPE_AUTOINC        =>  'Autoinc',
        self::FIELD_TYPE_BCD            =>  'Bcd',
        self::FIELD_TYPE_BYTES          =>  'Bytes',
    ];

    // 全局流
    protected Stream $stream;

    // 头部数据
    protected ParadoxHeader $paradoxHeader;

    // 字段类型
    protected array $fieldTypes;

    // 字段名称
    protected array $fieldNames;

    // 表的名称
    protected string $tableName;

    // 数据区块
    protected array $blocks;

    // 最终数据
    protected array $dataset;

    /**
     * 构造函数
     */
    public function __construct(string $path)
    {
        // 数据流
        $this->stream = new Stream(file_get_contents($path));

        // 1. 头部信息
        $this->header();
        // 2. 字段类型
        $this->types();
        // 3. 表名
        $this->tableName();
        // 4. 字段名称
        $this->names();
        // 5. 数据区块
        $this->blocks();
    }

    /**
     * 文件流
     */
    public function stream() : Stream
    {
        return $this->stream;
    }

    /**
     * 头部信息
     */
    public function header(?string $name = null, mixed $value = null) : mixed
    {
        if (!isset($this->paradoxHeader)) {
            $this->paradoxHeader = new ParadoxHeader($this->stream);
        }
        if (isset($name)) {
            if (isset($value)) {
                return $this->paradoxHeader->set($name, $value);
            } else {
                return $this->paradoxHeader->get($name);
            }
        }
        return $this->paradoxHeader;
    }

    /**
     * 字段类型
     */
    public function types(int $index = null) : mixed
    {
        // 初始化字段
        if (!isset($this->fieldTypes)) {
            // 字段数量
            $fieldCount = $this->header('fieldCount');
            // 基本字段
            $this->fieldTypes = [];
            for ($i = 0;$i < $fieldCount;$i++) {
                $this->fieldTypes[] = [
                    'type'  =>  $this->stream->readByte(),
                    'size'  =>  $this->stream->readByte(),
                ];
            }
            // 额外字段
            if ($this->header('fileType') == self::FILE_TYPE_PX_FILE) {
                $this->header('fieldCount', $fieldCount + 3);
                $item = [
                    'type'  =>  self::FIELD_TYPE_SHORT,
                    'size'  =>  2,
                ];
                $this->fieldTypes[] = $item;
                $this->fieldTypes[] = $item;
                $this->fieldTypes[] = $item;
            }
        }
        // 返回结果
        return isset($index)
            ? ($this->fieldTypes[$index] ?? null)
            : $this->fieldTypes;
    }

    /**
     * 获取表名
     */
    public function tableName() : string
    {
        // 初始化表名
        if (!isset($this->tableName)) {
            $tableNamePtr = $this->stream->readInt32();
            $fieldNamePtrArray = [];
            if (
                $this->header('fileType') == self::FILE_TYPE_DB_FILE_INDEXED
                || $this->header('fileType') == self::FILE_TYPE_DB_FILE_NOT_INDEXED
            ) {
                for ($i = 0;$i < $this->header('fieldCount'); $i++) {
                    $fieldNamePtrArray[] = $this->stream->readInt32();
                }
            }
            // 表的名称
            $tableNameBuff = $this->stream->readBytes($this->header('fileVersionID') >= 0x0C ? 261 : 79);
            $this->tableName = implode('', array_map(fn($b) => chr($b), $tableNameBuff));
        }
        // 返回结果
        return trim($this->tableName);
    }

    /**
     * 字段名称
     */
    public function names(int $index = null) : mixed
    {
        // 初始化名称
        if (!isset($this->fieldNames)) {
            $this->fieldNames = [];
            if (
                $this->header('fileType') == self::FILE_TYPE_DB_FILE_INDEXED
                || $this->header('fileType') == self::FILE_TYPE_DB_FILE_NOT_INDEXED
            ) {
                for ($i = 0;$i < $this->header('fieldCount'); $i++) {
                    $str = '';
                    while ("\x00" != $char = $this->stream->readChar()) {
                        $str .= $char;
                    }
                    $this->fieldNames[$i] = $str;
                }
            }
        }
        // 返回结果
        return isset($index)
            ? ($this->fieldNames[$index] ?? null)
            : $this->fieldNames;
    }

    /**
     * 数据区块
     */
    public function blocks() : array
    {
        // 初始化区块
        if (!isset($this->blocks)) {
            // 循环文件区块
            for ($i = 0;$i < $this->header('fileBlocks');$i++) {
                echo '第' . ($i + 1)  . '个区块', PHP_EOL;
                // 重新定位
                $offset = $i * $this->header('maxTableSize') * 0x0400 + $this->header('headerSize');
                $this->stream->offset($offset);
                // 保存区块
                $this->blocks[$i] = new DataBlock($this);
                // 保存数据
                $this->dataset = array_merge($this->dataset ?? [], $this->blocks[$i]->all());
            }
        }
        // 返回结果
        return $this->blocks;
    }

    /**
     * 获取数据
     */
    public function getData() : array
    {
        return $this->dataset ?? [];
    }
}