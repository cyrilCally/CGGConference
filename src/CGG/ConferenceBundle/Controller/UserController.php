<?php

namespace CGG\ConferenceBundle\Controller;

use CGG\ConferenceBundle\Entity\User;
use CGG\ConferenceBundle\Form\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\SecurityContextInterface;

class UserController extends Controller{

    public function registerAction(Request $request){
        /*TODO : Validation*/
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
                /*TODO : A faire après la validation par mail?*/
                $this->authenticateUser($user);
                $this->addFlash('success', 'WAHHHHHHHHHHHHHHHHHHHHHHHHOOOOOOUUUUUUUUU');

                $url = $this->redirectUser();
                return $this->redirect($url);
            }
        }
        return $this->render('CGGConferenceBundle:User:register.html.twig', ['form'=>$form->createView()]);
    }

    public function loginAction(){
        return $this->render('CGGConferenceBundle:User:login.html.twig', array());
    }

    public function authenticateUser(User $user){
        $providerKey = 'database_users';
        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
        $this->get('security.context')->setToken($token);
    }

    public function redirectUser(){
        /*TODO : trouver un moyen de ne pas avoir à marquer le nom du firewall en dur*/
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
    /*TODO : liste user + changement role sur certaines conf (acl)*/

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
}