<?php
declare(strict_types=1);

namespace Paradox;

use InvalidArgumentException;

class ParadoxHeader
{
    /**
     * 数据结构
     */
    protected array $struct = [
        'recordSize'            =>  Stream::UINT16,
        'headerSize'            =>  Stream::UINT16,
        'fileType'              =>  Stream::BYTE,
        'maxTableSize'          =>  Stream::BYTE,
        'recordCount'           =>  Stream::INT32,
        'nextBlock'             =>  Stream::UINT16,
        'fileBlocks'            =>  Stream::UINT16,
        'firstBlock'            =>  Stream::UINT16,
        'lastBlock'             =>  Stream::UINT16,
        'unknown12x13'          =>  Stream::UINT16,
        'modifiedFlags1'        =>  Stream::BYTE,
        'indexFieldNumber'      =>  Stream::BYTE,
        'primaryIndexWorkspace' =>  Stream::INT32,
        'unknownPtr1A'          =>  Stream::INT32,
        'pxRootBlockId'         =>  Stream::UINT16,
        'pxLevelCount'          =>  Stream::BYTE,
        'fieldCount'            =>  Stream::INT16,
        'primaryKeyFields'      =>  Stream::INT16,
        'encryption1'           =>  Stream::INT32,
        'sortOrder'             =>  Stream::BYTE,
        'modifiedFlags2'        =>  Stream::BYTE,
        'unknown2Bx2C'          =>  [Stream::BYTES, 2],
        'changeCount1'          =>  Stream::BYTE,
        'changeCount2'          =>  Stream::BYTE,
        'unknown2F'             =>  Stream::BYTE,
        'tableNamePtrPtr'       =>  Stream::INT32,  // ^pchar
        'fldInfoPtr'            =>  Stream::INT32,  //  PFldInfoRec;
        'writeProtected'        =>  Stream::BYTE,
        'fileVersionID'         =>  Stream::BYTE,
        'maxBlocks'             =>  Stream::UINT16,
        'unknown3C'             =>  Stream::BYTE,
        'auxPasswords'          =>  Stream::BYTE,
        'unknown3Ex3F'          =>  [Stream::BYTES, 2],
        'cryptInfoStartPtr'     =>  Stream::INT32,  //  pointer;
        'cryptInfoEndPtr'       =>  Stream::INT32,
        'unknown48'             =>  Stream::BYTE,
        'autoIncVal'            =>  Stream::INT32,  //  longint;
        'unknown4Dx4E'          =>  [Stream::BYTES, 2],
        'indexUpdateRequired'   =>  Stream::BYTE,
        'unknown50x54'          =>  [Stream::BYTES, 5],
        'refIntegrity'          =>  Stream::BYTE,
        'unknown56x57'          =>  [Stream::BYTES, 2],
    ];

    /**
     * V4结构
     */
    protected array $v4struct = [
        'fileVerID2'           =>  Stream::INT16,
        'fileVerID3'           =>  Stream::INT16,
        'encryption2'          =>  Stream::INT32,
        'fileUpdateTime'       =>  Stream::INT32,       // 4.0 only
        'hiFieldID'            =>  Stream::UINT16,
        'hiFieldIDinfo'        =>  Stream::UINT16,
        'sometimesNumFields'   =>  Stream::INT16,
        'dosCodePage'          =>  Stream::UINT16,
        'unknown6Cx6F'         =>  [Stream::BYTES, 4],  // array[$006C..$006F] of byte;
        'changeCount4'         =>  Stream::INT16,
        'unknown72x77'         =>  [Stream::BYTES, 6],  // array[$0072..$0077] of byte;
    ];

    /**
     * 数据缓存
     */
    protected array $dataset;

    /**
     * 构造函数
     */
    public function __construct(protected Stream $stream)
    {
        // 全部数据
        $this->dataset = $this->all();
    }

    /**
     * 存在字段
     */
    public function has(string $name) : mixed
    {
        return isset($this->dataset[$name]);
    }

    /**
     * 获取数据
     */
    public function get(string $name) : mixed
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException('header key not found!');
        }
        return $this->dataset[$name] ?? null;
    }

    /**
     * 设置数据
     */
    public function set(string $name, mixed $value) : static
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException('header key not found!');
        }
        $this->dataset[$name] = $value;
        return $this;
    }

    /**
     * 全部数据
     */
    public function all() : array
    {
        // 存在数据
        if (isset($this->dataset)) {
            return $this->dataset;
        }
        // 基本数据
        $result = $this->load($this->struct);
        // V4结构
        $fileType = $result['fileType'];
        $fileVersionID = $result['fileVersionID'];
        if ((
                $fileType == Paradox::FILE_TYPE_DB_FILE_INDEXED
                || $fileType == Paradox::FILE_TYPE_DB_FILE_NOT_INDEXED
                || $fileType == Paradox::FILE_TYPE_XNN_FILE_INC
                || $fileType == Paradox::FILE_TYPE_XNN_FILE_NON_INC
            )
            && $fileVersionID >= 5
        ) {
            $result = array_merge($result, $this->load($this->v4struct));
        }
        // 返回数据
        return $result;
    }

    /**
     * 获取结构数组中的数据
     */
    private function load(array $struct) : array
    {
        $result = [];
        foreach ($struct as $key => $type) {
            $result[$key] = $this->read($type);
            // echo $key . ': ' . (is_array($result[$key]) ? implode(',', $result[$key]) : $result[$key]), PHP_EOL;
        }
        return $result;
    }

    /**
     * 按具体类型读取数据
     */
    private function read(array|int $type) : mixed
    {
        if (is_array($type)) {
            return $this->stream->readBytes($type[1]);
        } else {
            $data = $this->stream->read($type);
            return $data[1];
        }
    }
}