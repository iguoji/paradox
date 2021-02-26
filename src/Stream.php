<?php
declare(strict_types=1);

namespace Paradox;

use RuntimeException;
use InvalidArgumentException;

class Stream
{
    /**
     * 类型常量
     */
    public const BYTE = 1;
    public const BYTES = 2;
    public const CHAR = 3;
    public const INT16 = 11;
    public const UINT16 = 12;
    public const INT32 = 21;
    public const UINT32 = 22;

    /**
     * 类型大小
     */
    public const SIZE = [
        self::BYTE      =>  1,
        self::BYTES     =>  0,
        self::CHAR      =>  1,
        self::INT16     =>  2,
        self::UINT16    =>  2,
        self::INT32     =>  4,
        self::UINT32    =>  4,
    ];

    /**
     * 类型格式
     */
    public const FORMATS = [
        self::BYTE      =>  'c',
        self::BYTES     =>  'c',
        self::CHAR      =>  'c',
        self::INT16     =>  's',
        self::UINT16    =>  'S',
        self::INT32     =>  'l',
        self::UINT32    =>  'L',
    ];

    /**
     * 偏移值
     */
    protected int $offset = 0;

    /**
     * 构造函数
     */
    public function __construct(protected string $contents)
    {
    }

    /**
     * 类型大小
     */
    public static function sizeof(int $type) : int
    {
        return self::SIZE[$type] ?? 0;
    }

    /**
     * 截取数据
     */
    public function slice(int $count, int $offset = null) : string
    {
        return substr($this->contents, $this->offset, $count);
    }

    /**
     * 读取数据
     */
    public function read(int|string $format, int $count = null, int $offset = null) : mixed
    {
        if (is_int($format)) {
            if (!isset(self::FORMATS[$format])) {
                throw new InvalidArgumentException('error format!');
            }
            $count = $count ?? self::sizeof($format);
            $format = self::FORMATS[$format];
        }
        $format .= $count;
        if (!isset($offset)) {
            $offset = $this->offset;
            $this->offset += $count;
        }
        // echo $offset . ' : ' . $format, PHP_EOL;
        $data = unpack($format, $this->contents, $offset);
        if (false === $data) {
            throw RuntimeException(__METHOD__ . '，解包失败！');
        }

        return $data;
    }

    /**
     * 跳过数量
     */
    public function skip(int $count) : static
    {
        $this->offset += $count;

        return $this;
    }

    /**
     * 设置定位
     */
    public function offset(int $index = null) : static|int
    {
        if (!isset($index)) {
            return $this->offset;
        }
        $this->offset = $index;
        return $this;
    }

    /**
     * 读取一个字节
     */
    public function readByte() : ?int
    {
        $data = $this->read(self::BYTE);
        return $data[1] ?? null;
    }

    /**
     * 读取指定数量的字节数组
     */
    public function readBytes(int $count) : array
    {
        return $this->read(self::BYTES, $count);
    }

    /**
     * 读取一个字符
     */
    public function readChar() : string
    {
        $byte = $this->readByte();
        return chr($byte);
    }

    /**
     * 读取16位有符号整数
     */
    public function readInt16() : ?int
    {
        $data = $this->read(self::INT16);
        return $data[1] ?? null;
    }

    /**
     * 读取16位无符号整数
     */
    public function readUInt16() : ?int
    {
        $data = $this->read(self::UINT16);
        return $data[1] ?? null;
    }

    /**
     * 读取32位有符号整数
     */
    public function readInt32() : ?int
    {
        $data = $this->read(self::INT32);
        return $data[1] ?? null;
    }

    /**
     * 读取32位无符号整数
     */
    public function readUInt32() : ?int
    {
        $data = $this->read(self::UINT32);
        return $data[1] ?? null;
    }
}