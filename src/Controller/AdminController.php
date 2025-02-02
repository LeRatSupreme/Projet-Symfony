<?php

namespace App\Controller;

use App\Entity\User; // Add this line
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\UserRolesType;

#[IsGranted('ROLE_SUPER_ADMIN', statusCode: 403, message: 'You must be logged in.')]
class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

    #[Route('/admin/test1', name: 'app_admin_test1')]
    public function index_test(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'User tried to access a page without having ROLE_ADMIN');
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

    #[Route('/admin/test2', name: 'app_admin_test2')]
    #[IsGranted('ROLE_SUPER_ADMIN', statusCode: 403, message: 'You are not allowed to access the Super admin dashboard.')]
    public function index_test2(): Response
    {
        $this->addFlash('success', 'Role : Super Admin');
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

    #[Route('/admin/users', name: 'admin_user_list')]
    #[IsGranted('ROLE_SUPER_ADMIN', statusCode: 403, message: 'You are not allowed to access the Super admin user dashboard.')]
    public function listUsers(EntityManagerInterface $entityManager): Response
    {
        $users = $entityManager->getRepository(User::class)->findAll();

        return $this->render('admin/users/list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/users/delete/confirm/{id}', name: 'admin_user_delete_confirm')]
    #[IsGranted('ROLE_SUPER_ADMIN', statusCode: 403, message: 'You are not allowed to access the Super admin user dashboard.')]
    public function confirmDeleteUser(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        return $this->render('admin/users/confirm_delete.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/users/delete/{id}', name: 'admin_user_delete')]
    #[IsGranted('ROLE_SUPER_ADMIN', statusCode: 403, message: 'You are not allowed to access the Super admin user dashboard.')]
    public function deleteUser(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if ($user && !in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            $entityManager->remove($user);
            $entityManager->flush();
        } else {
            $this->addFlash('error', 'You cannot delete a Super Admin account.');
        }

        return $this->redirectToRoute('admin_user_list');
    }

    #[Route('/admin/users/roles/{id}', name: 'admin_user_roles')]
    public function manageUserRoles(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $form = $this->createForm(UserRolesType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedRoles = $form->get('roles')->getData();
            $user->setRoles(array_unique($selectedRoles));

            $entityManager->flush();

            $this->addFlash('success', 'Roles updated for ' . $user->getEmail() . ': ' . implode(', ', $user->getRoles()));

            return $this->redirectToRoute('admin_user_roles', ['id' => $id]);
        }

        return $this->render('admin/users/roles.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}
