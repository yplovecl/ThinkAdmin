<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\admin\controller;

use library\Controller;
use library\service\AdminService;
use library\service\CaptchaService;
use library\service\SystemService;
use think\Db;
use think\facade\Request;

/**
 * 用户登录管理
 * Class Login
 * @package app\admin\controller
 */
class Login extends Controller
{

    /**
     * 后台登录入口
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        if (Request::isGet()) {
            if (AdminService::instance()->isLogin()) {
                $this->redirect('@admin');
            } else {
                $this->title = '系统登录';
                if (!($this->loginskey = session('loginskey'))) {
                    session('loginskey', $this->loginskey = uniqid());
                }
                $this->devmode = SystemService::instance()->checkRunMode('dev');
                $this->captcha = CaptchaService::instance()->getAttrs();
                $this->fetch();
            }
        } else {
            $data = $this->_vali([
                'username.require' => '登录账号不能为空!',
                'username.min:4'   => '登录账号长度不能少于4位有效字符！',
                'password.require' => '登录密码不能为空！',
                'password.min:4'   => '登录密码长度不能少于4位有效字符！',
                'verify.require'   => '图形验证码不能为空！',
                'uniqid.require'   => '图形验证标识不能为空！'
            ]);
            if (!CaptchaService::instance()->check($data['verify'], $data['uniqid'])) {
                $this->error('图形验证码验证失败，请重新输入!');
            }
            // 用户信息验证
            $map = ['is_deleted' => '0', 'username' => $data['username']];
            $user = Db::name('SystemUser')->where($map)->order('id desc')->find();
            if (empty($user)) $this->error('登录账号或密码错误，请重新输入!');
            if (md5($user['password'] . session('loginskey')) !== $data['password']) {
                $this->error('登录账号或密码错误，请重新输入!');
            }
            if (empty($user['status'])) $this->error('账号已经被禁用，请联系管理员!');
            Db::name('SystemUser')->where(['id' => $user['id']])->update([
                'login_at' => Db::raw('now()'), 'login_ip' => Request::ip(), 'login_num' => Db::raw('login_num+1'),
            ]);
            session('user', $user);
            session('loginskey', null);
            AdminService::instance()->apply(true);
            sysoplog('系统管理', '用户登录系统成功');
            $this->success('登录成功', url('@admin'));
        }
    }

    /**
     * 退出登录
     */
    public function out()
    {
        $this->app->session->clear();
        $this->app->session->destroy();
        $this->success('退出登录成功！', url('@admin/login'));
    }

}
