<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PasswordResetRequestType;
use App\Form\PasswordResetType;
use App\Repository\UserRepository;
use App\Utils\TokenGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * @Route("/reset/request", name="reset_request")
     */
    public function reset_request(Request $request, UserRepository $userRepository, MailerInterface $mailer)
    {
        $user = new User();
        $form = $this->createForm(PasswordResetRequestType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $userRepository->findOneBy(['username' => $user->getUserIdentifier()]);
            if ($user && (!$user->getRequestAt() || (clone $user->getRequestAt())->add(new \DateInterval('PT15M')) < new \DateTime())) {
                $fromName = $this->getParameter('mail.from.name');
                $fromEmail = $this->getParameter('mail.from.email');

                $user->setConfirmationToken(TokenGenerator::generate())
                    ->setRequestAt(new \DateTime());
                $this->getDoctrine()->getManager()->flush();

                $email = (new TemplatedEmail())
                    ->addTo(new Address($user->getUserIdentifier(), $user->getFirstname() || $user->getLastname() ? "{$user->getFirstname()} {$user->getLastname()}" : null))
                    ->addFrom(new Address($fromEmail, $fromName))
                    ->htmlTemplate('email/reset_password/index.html.twig')
                    ->context(['user' => $user]);

                try {
                    $mailer->send($email);
                } catch (TransportExceptionInterface $e) {
                }
            }

            $this->addFlash('success', 'Un email a été envoyer à votre email.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/reset/{confirmationToken}", name="reset_password")
     */
    public function reset_password(Request $request, User $user, UserPasswordHasherInterface $passwordHasher)
    {
        $form = $this->createForm(PasswordResetType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $user->getPlainPassword()) {
            $user->setPassword($passwordHasher->hashPassword($user, $user->getPlainPassword()))
                ->setConfirmationToken(null)
                ->setRequestAt(null);
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash('success', 'Mot de passe a ete modifier avec succès.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
