<?php
declare(strict_types=1);

namespace Paradox;

class DataBlock
{
    /**
     * 基本参数
     */
    public int $nextBlock;
    public int $blockNumber;
    public int $addDataSize;
    public int $recordCount;

    // 起始位置
    protected int $offset;
    // 区块数据
    protected array $data;
    // 缓存数据
    protected array $cache;

    /**
     * 构造函数
     */
    public function __construct(public Paradox $paradox)
    {
        $this->nextBlock = $paradox->stream()->readUInt16();
        $this->blockNumber = $paradox->stream()->readUInt16();
        $this->addDataSize = $paradox->stream()->readInt16();
        $this->recordCount = ($this->addDataSize / $paradox->header('recordSize')) + 1;
        $this->offset = $paradox->stream()->offset();

        $this->all();
    }

    /**
     * 获取数据
     */
    public function get(int $rowIndex) : ?array
    {
        // 返回结果
        return $this->cache[$rowIndex] ?? null;
    }

    /**
     * 所有数据
     */
    public function all() : array
    {
        // 初始化数据
        if (!isset($this->cache)) {
            // 初始化
            $this->cache = [];
            // 循环读取
            for ($row = 0;$row < $this->recordCount; $row++) {
                // 起始位置
                $this->paradox->stream()->offset(
                    $this->offset
                    + $row * $this->paradox->header('recordSize')
                );
                // 循环读取
                $rowData = [];
                for ($col = 0;$col < $this->paradox->header('fieldCount'); $col++) {
                    // 获取当前字段名称
                    $name = $this->paradox->names($col);
                    // 获取当前字段类型
                    $type = $this->paradox->types($col);
                    // 判断该字段是否为空，按类型大小循环该字段
                    $isEmpty = true;
                    $oldOffset = $this->paradox->stream()->offset();
                    for ($j = 0;$j < $type['size'];$j++) {
                        if (!empty($this->paradox->stream()->readByte())) {
                            $isEmpty = false;
                            break;
                        }
                    }
                    $this->paradox->stream()->offset($oldOffset);
                    // 当前字段为空
                    if ($isEmpty) {
                        $rowData[$name] = null;
                        continue;
                    }
                    // 按类型处理
                    switch ($type['type']) {
                        // Alpha
                        case Paradox::FIELD_TYPE_ALPHA:
                            // 读取字节数组 + 每个二进制元素转成字符串 + 合并成字符串
                            $str = implode('', array_map(fn($b) => chr($b), $this->paradox->stream()->readBytes($type['size'])));
                            // 编码转换
                            $rowData[$name] = mb_convert_encoding($str, mb_detect_encoding($str) ?: 'UTF-8', 'GBK');
                            break;
                        // Short
                        case Paradox::FIELD_TYPE_SHORT:
                            $rowData[$name] = $this->hello($type['size'], 's');
                            break;
                        // Long/AutoInc
                        case Paradox::FIELD_TYPE_LONG:
                        case Paradox::FIELD_TYPE_AUTOINC:
                            $rowData[$name] = $this->hello($type['size'], 'l');
                            break;
                        // 其他类型不支持
                        default:
                            $rowData[$name] = null;
                            break;
                    }
                }
                // 保存数据
                $this->cache[$row] = empty($rowData) ? null : $rowData;
            }
        }
        // 返回结果
        return $this->cache;
    }

    /**
     * 蜜汁操作
     */
    public function hello(int $size, string $format) : mixed
    {
        // 截取字节
        $strs = $this->paradox->stream()->slice($size);
        $this->paradox->stream()->skip($size);
        // 数据解包
        $strs = unpack('C' . $size, $strs);
        // 第一个数字
        $strs[1] = $strs[1] ^ 0x80;
        // 反转 + 每个元素转成二进制 + 合并成字符串
        $strs = implode('', array_map(fn($s) => pack('C', $s), array_reverse($strs)));
        // 二进制转成数字
        return unpack($format, $strs)[1];
    }
}