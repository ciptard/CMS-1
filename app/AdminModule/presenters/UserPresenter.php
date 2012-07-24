<?php

namespace AdminModule;

use Flame\Forms\UserForm,
    Flame\Models\Users\User,
	Flame\Forms\ChangePasswordForm;


class UserPresenter extends AdminPresenter
{
    private $userFacade;

	private $userInfoFacade;

    private $authenticator;

	private $user;

	private $id;

    public function __construct(
	    \Flame\Models\Users\UserFacade $userFacade,
	    \Flame\Security\Authenticator $authenticator,
		\Flame\Models\UsersInfo\UserInfoFacade $userInfoFacade
    )
    {
        $this->userFacade = $userFacade;
        $this->authenticator = $authenticator;
	    $this->userInfoFacade = $userInfoFacade;
    }

	public function renderDefault()
	{
		$this->template->users = $this->userFacade->getLastUsers();
	}

	public function actionEdit($id)
	{
		$this->id = $id;

		if($this->getUser()->getId() != $id or !$this->getUser()->isAllowed('Admin:User', 'editAnother')){
			$this->flashMessage('Access denied');
			$this->redirect('Dashboard:');
		}else{
			if($user = $this->userFacade->getOne($id)){
				$this->user = $user;
			}else{
				$this->flashMessage('User does not exist!');
				$this->redirect('Dashboard:');
			}
		}

	}

	protected function createComponentEditUserForm()
	{
		$form = new UserForm();

		$userInfo = $this->userInfoFacade->getOneByUser($this->user);
		$default = array_merge(
			array('email' => $this->user->getEmail()),
			$userInfo ? $userInfo->toArray() : array()
		);

		$form->configureEditFull($default);
		$form->onSuccess[] = callback($this, 'userEditFormSubmitted');
		return $form;
	}


	protected function createComponentAddUserForm()
	{
		$f = new UserForm();
		$f->configureRoles();
		$f->configureAdd();
		$f->onSuccess[] = callback($this, 'userAddFormSubmitted');

        return $f;
	}

	public function userEditFormSubmitted(UserForm $form)
	{
		if($this->id and !$this->user){
			throw new \Nette\Application\BadRequestException;
		}

		$values = $form->getValues();


		if($info = $this->userInfoFacade->getOneByUser($this->user)){
			$info->setName($values['name'])
				->setAbout($values['about'])
				->setBirthday($values['birthday'])
				->setWeb($values['web'])
				->setFacebook($values['facebook'])
				->setTwitter($values['twitter']);
			$this->userInfoFacade->persist($info);
		}else{
			$info = new \Flame\Models\UsersInfo\UserInfo($this->user, $values['name']);
			$info->setAbout($values['about'])
				->setBirthday($values['birthday'])
				->setWeb($values['web'])
				->setFacebook($values['facebook'])
				->setTwitter($values['twitter']);
			$this->userInfoFacade->persist($info);
		}

		$this->flashMessage('User was edited');
		$this->redirect('this');


	}

	public function userAddFormSubmitted(UserForm $form)
	{
		$values = $form->getValues();


		if($this->userFacade->getByEmail($values['email'])){
			$form->addError('Email ' . $values['email'] . ' exist.');
		}else{
			$user = new \Flame\Models\Users\User(
				$values['email'],
				$this->authenticator->calculateHash($values['password']),
				$values['role']
			);

			$this->userFacade->persist($user);

			$this->flashMessage('User was added');
			$this->redirect('User:');
		}

	}

	protected function createComponentPasswordForm()
	{
		$form = new ChangePasswordForm();
		$form->configure();
		$form->onSuccess[] = callback($this, 'passwordFormSubmitted');

        return $form;
	}


	public function passwordFormSubmitted(ChangePasswordForm $form)
	{
		$values = $form->getValues();
		$user = $this->getUser();

		try {
			$this->authenticator->authenticate(array($user->getIdentity()->username, $values->oldPassword));

            $userEntity = $this->userFacade->getOne($user->getId());
            $this->authenticator->setPassword($userEntity, $values['newPassword']);

			$this->flashMessage('Password was changed.', 'success');
			$this->redirect('this');
		} catch (\Nette\Security\AuthenticationException $e) {
			$form->addError('Invalid credentials');
		}
	}

	public function handleDelete($id)
	{
		if($this->getUser()->getId() == $id){
			$this->flashMessage('You cannot delete yourself');
		}elseif(!$this->getUser()->isAllowed('Admin:User', 'delete')){
			$this->flashMessage('Access denied');
		}else{
			$user = $this->userFacade->getOne((int) $id);

			if(!$user){
				$this->flashMessage('User with required ID does not exist');
			}else{
				$this->userFacade->delete($user);
			}
		}

		if(!$this->isAjax()){
			$this->redirect('this');
		}else{
			$this->invalidateControl('users');
		}
	}
}
