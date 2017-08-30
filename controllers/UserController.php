<?php

namespace work\modules\rbac\controllers;

use common\helper\ArrayHelper;
use work\models\AdminUser;
use work\modules\rbac\controllers\base\ApiController;
use Yii;
use work\modules\rbac\models\form\Login;
use work\modules\rbac\models\form\PasswordResetRequest;
use work\modules\rbac\models\form\ResetPassword;
use work\modules\rbac\models\form\Signup;
use work\modules\rbac\models\form\ChangePassword;
use work\modules\rbac\models\User;
use work\modules\rbac\models\searchs\User as UserSearch;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\mail\BaseMailer;

/**
 * User controller
 */
class UserController extends ApiController
{
    
    private $_oldMailPath;
    
    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            if (Yii::$app->has('mailer') && ($mailer = Yii::$app->getMailer()) instanceof BaseMailer) {
                /* @var $mailer BaseMailer */
                $this->_oldMailPath = $mailer->getViewPath();
                $mailer->setViewPath('@mdm/admin/mail');
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * 用户列表
     *
     * @return array|\yii\db\ActiveRecord[]
     */
    public function actionIndex()
    {
        $searchModel = new UserSearch();
        $result = $searchModel->search(Yii::$app->request->queryParams);
        
        return $result;
    }
    
    /**
     * 用户详情
     *
     * @param $id
     *
     * @return \work\modules\rbac\models\User
     */
    public function actionView($id)
    {
        $result = $this->findModel($id);
        $result->created_at = date('Y年m月d日', $result->created_at);
        unset($result->password_hash);
        unset($result->auth_key);
        unset($result->password_reset_token);
        unset($result->dingId);
        
        return $result;
    }
    
    /**
     * Deletes an existing User model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     *
     * @param integer $id
     *
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        
        return $this->redirect(['index']);
    }
    
    /**
     * Login
     *
     * @return string
     */
    public function actionLogin()
    {
        if ( !Yii::$app->getUser()->isGuest) {
            return $this->goHome();
        }
        
        $model = new Login();
        if ($model->load(Yii::$app->getRequest()->post()) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }
    
    /**
     * Logout
     *
     * @return string
     */
    public function actionLogout()
    {
        Yii::$app->getUser()->logout();
        
        return $this->goHome();
    }
    
    /**
     * Signup new user
     *
     * @return string
     */
    public function actionSignup()
    {
        $model = new Signup();
        if ($model->load(Yii::$app->getRequest()->post())) {
            if ($user = $model->signup()) {
                return $this->goHome();
            }
        }
        
        return $this->render('signup', [
            'model' => $model,
        ]);
    }
    
    /**
     * Request reset password
     *
     * @return string
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequest();
        if ($model->load(Yii::$app->getRequest()->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->getSession()->setFlash('success', 'Check your email for further instructions.');
                
                return $this->goHome();
            } else {
                Yii::$app->getSession()->setFlash('error',
                    'Sorry, we are unable to reset password for email provided.');
            }
        }
        
        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }
    
    /**
     * Reset password
     *
     * @return string
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPassword($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
        
        if ($model->load(Yii::$app->getRequest()->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->getSession()->setFlash('success', 'New password was saved.');
            
            return $this->goHome();
        }
        
        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }
    
    /**
     * Reset password
     *
     * @return string
     */
    public function actionChangePassword()
    {
        $model = new ChangePassword();
        if ($model->load(Yii::$app->getRequest()->post()) && $model->change()) {
            return $this->goHome();
        }
        
        return $this->render('change-password', [
            'model' => $model,
        ]);
    }
    
    /**
     * 修改用户状态
     *
     * @param $id
     *
     * @return bool
     */
    public function actionActivate($id)
    {
        /* @var $user User */
        $user = $this->findModel($id);
        if ($user->status == User::STATUS_INACTIVE) {
            $user->status = User::STATUS_ACTIVE;
            if ($user->save()) {
                return true;
//                return $this->goHome();
            } else {
                $errors = $user->firstErrors;
                
                return false;
//                throw new UserException(reset($errors));
            }
        }
        
        return true;
//        return $this->goHome();
    }
    
    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     *
     * @param integer $id
     *
     * @return User the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = User::findOne($id)) !== null) {
            return $model;
        } else {
            return false;
        }
    }
}
