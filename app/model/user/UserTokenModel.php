<?php

namespace main\app\model\user;

use main\app\model\CacheModel;

/**
 *
 * 用户token模块
 *
 * @author seven@haowan11.com
 *
 */
class UserTokenModel extends CacheModel
{
    public $prefix = 'user_';

    public $table = 'token';

    const   DATA_KEY = 'user_token/';

    const   VALID_TOKEN_RET_OK = 1;

    const   VALID_TOKEN_RET_NOT_EXIST = 2;

    const   VALID_TOKEN_RET_EXPIRE = 3;

    const HTTP_RESPONSE_OK = 2000;
    const HTTP_RESPONSE_EXPIRE = 4220;
    const HTTP_RESPONSE_INVALID = 4000;

    public $uid = '';

    /**
     * 用于实现单例模式
     * @var self
     */
    protected static $instance;


    public function __construct($uid = '', $persistent = false)
    {
        parent::__construct($uid, $persistent);
        $this->uid = $uid;
    }

    /**
     * 创建一个自身的单例对象
     * @param string $uid
     * @param bool $persistent
     * @return mixed
     * @throws \Exception
     */
    public static function getInstance($uid = '', $persistent = false)
    {
        $index = $uid . strval(intval($persistent));
        if (!isset(self::$instance[$index]) || !is_object(self::$instance[$index])) {
            self::$instance[$index] = new self($uid, $persistent);
        }
        return self::$instance[$index];
    }

    /**
     * 生成token
     * @param string $uid
     * @param string $pass
     * @return string
     */
    public static function makeUserToken($uid, $pass)
    {
        $token_cfg = getConfigVar('data');
        $publicKey = $token_cfg['token']['public_key'];
        $secretKey = $token_cfg['token']['secret_key'];
        $expireTime = $token_cfg['token']['expire_time'];
        return md5(md5($uid) . $publicKey . $secretKey . $pass . time()) . md5(time() . $expireTime . $uid);
    }

    /**
     * 刷新token
     * @param string $uid
     * @param string $password
     * @return string
     */
    public static function makeUserRefreshToken($uid, $password)
    {
        $tokenCfg = getConfigVar('data');
        $publicKey = $tokenCfg['token']['public_key'];
        $secretKey = $tokenCfg['token']['secret_key'];
        $expireTime = $tokenCfg['token']['expire_time'];
        return md5($uid . $publicKey . md5($password) . $secretKey . time()) . md5($uid . $expireTime);
    }

    /**
     * @param $uid
     * @param $token
     * @return array
     * @throws \Exception
     */
    public function validUidToken($uid, $token)
    {
        $row = $this->getUserToken($uid);

        if (!isset($row['token']) || $row['token'] != $token) {
            return array(self::VALID_TOKEN_RET_NOT_EXIST, 'token值错误!');
        }

        $dataConfig = getConfigVar('data');

        if ((time() - intval($row['token_time'])) > intval($dataConfig['token']['expire_time'])) {
            return array(self::VALID_TOKEN_RET_EXPIRE, 'token值过期了!');
        }
        return array(self::VALID_TOKEN_RET_OK, 'ok');
    }

    /**
     * 校验token是否有效
     * @param $token
     * @return array
     * @throws \Exception
     */
    public function validToken($token)
    {
        $row = $this->getUserTokenByToken($token);

        if (!isset($row['token'])) {
            return array(self::VALID_TOKEN_RET_NOT_EXIST, 'token值错误!', []);
        }

        if ($this->isTokenExpire($row['token_time'])) {
            return array(self::VALID_TOKEN_RET_EXPIRE, 'token值过期了!', []);
        }

        return array(self::VALID_TOKEN_RET_OK, 'ok', $row);
    }

    /**
     * 校验refresh_token是否有效
     * @param $refreshToken
     * @return array
     * @throws \Exception
     */
    public function validRefreshToken($refreshToken)
    {
        $row = $this->getUserTokenByRefreshToken($refreshToken);

        if (empty($row)) {
            return array(self::VALID_TOKEN_RET_NOT_EXIST, 'refresh值错误!', []);
        }

        if (!isset($row['token'])) {
            return array(self::VALID_TOKEN_RET_NOT_EXIST, 'refresh值错误!', []);
        }

        if ($this->isRefreshTokenExpire($row['refresh_token_time'])) {
            return array(self::VALID_TOKEN_RET_EXPIRE, 'refresh值过期了!', []);
        }

        return array(self::VALID_TOKEN_RET_OK, 'ok', $row);
    }

    /**
     * 生成和刷新token
     * @param $user
     * @return array
     * @throws \Exception
     */
    public function makeToken($user)
    {
        $token_row = $this->getUserToken($user['uid']);
        // v( $token_row );
        $token = self::makeUserToken($user['uid'], $user['password']);
        $refresh_token = self::makeUserRefreshToken($user['uid'], $user['password']);
        $userTokenInfo = [];
        $userTokenInfo['uid'] = $user['uid'];
        $userTokenInfo['token'] = $token;
        $userTokenInfo['token_time'] = time();
        $userTokenInfo['refresh_token'] = $refresh_token;
        $userTokenInfo['refresh_token_time'] = time();

        if (!isset($token_row['token'])) {
            $ret = (bool)$this->insertUserToken($userTokenInfo);
        } else {
            $ret = $this->updateUserToken($user['uid'], $userTokenInfo);
        }
        return array($ret, $token, $refresh_token);
    }

    /**
     * 获取用户token的记录信息
     * @param $uid
     * @return array
     * @throws \Exception
     */
    public function getUserToken($uid)
    {
        //使用缓存机制
        $fields = '* ';
        $where = ['uid' => $uid];//" Where `uid`='$uid'  limit 1 ";
        $key = self::DATA_KEY . $uid;
        $final = parent::getRowByKey($fields, $where, $key);
        return $final;
    }

    /**
     * 获取用户token的记录信息
     * @param $token
     * @return array
     * @throws \Exception
     */
    public function getUserTokenByToken($token)
    {
        //使用缓存机制
        $fields = '* ';
        $where = ['token' => $token];
        $key = "";
        $final = parent::getRowByKey($fields, $where, $key);
        return $final;
    }

    /**
     * 通过refresh_token获取用户token的记录信息
     * @param $refreshToken
     * @return array
     * @throws \Exception
     */
    public function getUserTokenByRefreshToken($refreshToken)
    {
        //使用缓存机制
        $fields = '* ';
        $where = ['refresh_token' => $refreshToken];
        $key = "";
        $final = parent::getRowByKey($fields, $where, $key);
        return $final;
    }

    /**
     * 插入一条用户token记录
     * @param $insertInfo
     * @return mixed
     * @throws \Exception
     */
    public function insertUserToken($insertInfo)
    {
        $key = self::DATA_KEY . $insertInfo['uid'];
        $ret = parent::insertByKey($insertInfo, $key);
        return $ret;
    }

    /**
     * @param $uid
     * @param $update_info
     * @return bool
     * @throws \Exception
     */
    public function updateUserToken($uid, $update_info)
    {
        if (empty($update_info)) {
            return false;
        }
        if (!is_array($update_info)) {
            return false;
        }
        $key = self::DATA_KEY . $uid;
        $where = ['uid' => $uid];
        list($flag) = $this->updateByKey($where, $update_info, $key);

        return $flag;
    }

    /**
     * 删除用户token记录
     * Enter description here ...
     */
    public function delUserToken($uid)
    {
        $key = self::DATA_KEY . $uid;
        $where = ['uid' => $uid];

        $flag = parent::deleteBykey($where, $key);
        return $flag;
    }

    /**
     * 判断token是否过期
     * @param $tokenTime
     * @return bool 已过期为true
     */
    public function isTokenExpire($tokenTime)
    {
        $dataConfig = getConfigVar('data');
        if ((time() - intval($tokenTime)) > intval($dataConfig['token']['expire_time'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 判断refresh_token是否过期
     * @param $refreshTokenTime
     * @return bool 已过期为true
     */
    public function isRefreshTokenExpire($refreshTokenTime)
    {
        $dataConfig = getConfigVar('data');
        if ((time() - intval($refreshTokenTime)) > intval($dataConfig['token']['refresh_expire_time'])) {
            return true;
        } else {
            return false;
        }
    }
}
