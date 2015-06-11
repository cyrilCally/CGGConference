<?php

namespace CGG\ConferenceBundle\Controller;

use CGG\ConferenceBundle\Entity\User;
use CGG\ConferenceBundle\Form\Type\UserProfilType;
use CGG\ConferenceBundle\Form\Type\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Exception\AclAlreadyExistsException;
use Symfony\Component\Security\Acl\Permission\MaskBuilder;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Validator\Constraints\Null;

class UserController extends Controller{

    public function registerAction(Request $request){
        $role = $this->get('role_repository')->findRoleByName('user');
        $user = new User();
        $form = $this->createForm(New UserType(), $user);
        if($request->isMethod('POST')){
            $form->submit($request);
            if($form->isValid()){
                if (0 !== strlen($plainPassword = $user->getPlainPassword())) {
                    $encoder = $this->get('security.encoder_factory')->getEncoder($user);
                    $user->setPassword($encoder->encodePassword($plainPassword, $user->getSalt()));
                    $user->eraseCredentials();
                }
                $user->addRole($role);
                $this->get('user_repository')->save($user);
                $this->authenticateUserAction($user);
                $this->addFlash('success', 'Opération effectuée avec succès.');

                $url = $this->redirectUserAction();
                return $this->redirect($url);
            }
        }
        return $this->render('CGGConferenceBundle:User:register.html.twig', ['form'=>$form->createView()]);
    }

    public function loginAction(){
        return $this->render('CGGConferenceBundle:User:login.html.twig');
    }

    public function authenticateUserAction(User $user){
        $providerKey = 'database_users';
        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
        $this->get('security.context')->setToken($token);
    }

    public function redirectUserAction(){
        $firewall = 'security_admin';
        $sessionKeyRedirectUrlAfterLogin = '_security.'.$firewall.'.target_path';
        if($this->get('session')->has($sessionKeyRedirectUrlAfterLogin)){
            $url = $this->get('session')->get($sessionKeyRedirectUrlAfterLogin);
            $this->get('session')->remove($sessionKeyRedirectUrlAfterLogin);
        }else{
            $url = $this->generateUrl('cgg_conference_home');
        }

        return $url;
    }

    public function listUserAction(){
        $users = $this->get('user_repository')->listUser();
        $roles = $this->get('role_repository')->listRoles();
        return $this->render('CGGConferenceBundle:User:listUser.html.twig', ['users'=>$users, 'roles'=>$roles]);

    }

    public function saveChangesRolesUsersAction(Request $request){

        $userRepository = $this->get('user_repository');
        $roleName = $request->request->get('roleName');
        $username = $request->request->get('username');
        $role = $this->get('role_repository')->findRoleByName($roleName);
        $user = $userRepository->findUserByUsernameOrEmail($username);

        $user->addRole($role);
        $userRepository->save($user);

        return $this->render('CGGConferenceBundle:Conference:home.html.twig');
    }

    public function removeRoleAction(Request $request){

        $userRepository = $this->get('user_repository');
        $user = $userRepository->findUserByUsernameOrEmail($request->request->get('username'));
        $role = $this->get('role_repository')->findRoleByName($request->request->get('roleName'));
        $user->removeRole($role);
        $userRepository->save($user);

        return new Response("OK");

    }

    public function forgotYourPasswordAction(){
        $request = $this->container->get('request');
        $email = $request->request->get('email');

        $user = $this->get('user_repository')->findUserByUsernameOrEmail($email);
        if($user == null){
            $data = array('emailValid' => false);
        }else{
            $data = array('emailValid' => true);
            $password = $this->get('generate_password')->genererMDP();
            $user->setPlainPassword($password);
            $plainPassword = $user->getPlainPassword();
            $encoder = $this->get('security.encoder_factory')->getEncoder($user);
            $user->setPassword($encoder->encodePassword($plainPassword, $user->getSalt()));
            $user->eraseCredentials();
            $this->get('user_repository')->save($user);
            $this->get('mail_forgot_your_password')->mailAdminForgotYourPassword($password);
        }
        $response = new Response();
        $response->setContent(json_encode($data));
        return $response;
    }
    public function profilAction(Request $request)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $form = $this->createForm(New UserProfilType(), $user);
        if ($request->isMethod('POST')) {
            $form->submit($request);
            if ($form->isValid()) {
                if (0 !== strlen($plainPassword = $user->getPlainPassword())) {
                    $encoder = $this->get('security.encoder_factory')->getEncoder($user);
                    $user->setPassword($encoder->encodePassword($plainPassword, $user->getSalt()));
                    $user->erasecredentials();
                }
                $this->get('user_repository')->save($user);
                $this->authenticateUserAction($user);
                $this->addFlash('success', 'Modification enregistrée');

                $url = $this->redirectUserAction();
                return $this->redirect($url);
            }
        }
        return $this->render('CGGConferenceBundle:User:profil.html.twig', ['form' => $form->createView()]);
    }

    public function removeUserAction($idUser){
        $user = $this->get('user_repository')->find($idUser);
        $this->get('user_repository')->removeUser($user);


        return $this->forward('CGGConferenceBundle:User:listUser');
    }

    public function defineJuryAction(Request $request){
        $users = $this->get('user_repository')->findAll();
        $conferences = $this->get('conference_repository')->findAllConferenceByStatus('V');
        if($request->isMethod("POST")){
            $idConference = $request->request->get('idConference');
            $idUser = $request->request->get('idUser');
            $conference = $this->get('conference_repository')->find($idConference);
            $user = $this->get('user_repository')->find($idUser);

            if(empty($idUser) || empty($idConference)){
                $this->addFlash('alert', "La conférence ou l'utilisateur n'a pas été sélectionné.");
                return $this->redirect($this->generateUrl('cgg_conference_admin_define_jury'));
                die;
            }

            $aclProvider = $this->get('security.acl.provider');
            $objectIdentity = ObjectIdentity::fromDomainObject($conference);
            $acl = $aclProvider->findAcl($objectIdentity);

            $securityIdentity = UserSecurityIdentity::fromAccount($user);
            $acl->insertObjectAce($securityIdentity, MaskBuilder::MASK_VIEW);
            $aclProvider->updateAcl($acl);

            $this->addFlash('success', 'Jury associé à la compétition d\'image avec succès');
            return $this->redirect($this->generateUrl('cgg_conference_admin_define_jury'));

        }

        return $this->render('CGGConferenceBundle:User:defineJury.html.twig',
            [
                'users'=>$users,
                'conferences'=>$conferences
            ]);
    }
}