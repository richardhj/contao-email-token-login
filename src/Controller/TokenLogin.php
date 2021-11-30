<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/contao-email-token-login.
 *
 * Copyright (c) Richard Henkenjohann
 *
 * @license LGPL-3.0-or-later
 */

namespace Richardhj\ContaoEmailTokenLoginBundle\Controller;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class TokenLogin extends AbstractController
{
    private $userProvider;
    private $tokenStorage;
    private $connection;
    private $dispatcher;
    private $translator;
    private $authenticationSuccessHandler;
    private $logger;
    private $userChecker;

    public function __construct(UserProviderInterface $userProvider, TokenStorageInterface $tokenStorage, Connection $connection, EventDispatcherInterface $dispatcher, TranslatorInterface $translator, AuthenticationSuccessHandlerInterface $authenticationSuccessHandler, LoggerInterface $logger, UserCheckerInterface $userChecker)
    {
        $this->userProvider = $userProvider;
        $this->tokenStorage = $tokenStorage;
        $this->connection = $connection;
        $this->dispatcher = $dispatcher;
        $this->translator = $translator;
        $this->authenticationSuccessHandler = $authenticationSuccessHandler;
        $this->logger = $logger;
        $this->userChecker = $userChecker;
    }

    public function __invoke(string $token, Request $request)
    {
        $statement = $this->connection->createQueryBuilder()
            ->select('t.id AS id', 't.member AS member', 't.jumpTo AS jumpTo')
            ->from('tl_member_login_token', 't')
            ->where('t.token =:token')
            ->andWhere('t.expires >=:time')
            ->setParameter('token', $token)
            ->setParameter('time', time())
            ->execute()
        ;

        $result = $statement->fetch(\PDO::FETCH_OBJ);

        if (false === $result) {
            throw new AccessDeniedException('Token not found or expired: '.$token);
        }

        $member = MemberModel::findByPk($result->member);

        if (null === $member) {
            throw new PageNotFoundException('We don\'t know who you are :-(');
        }

        // Only proceed on POST requests. On GET, show a <form> to gather a POST request. See #3
        if (!$request->isMethod('POST')) {
            return $this->render('@RichardhjContaoEmailTokenLogin/login_entrypoint.html.twig', [
                'loginBT' => $this->translator->trans('MSC.loginBT', [], 'contao_default'),
                'form_id' => 'login'.substr($token, 0, 4),
                'form_action' => $request->getRequestUri(),
            ]);
        }

        $this->invalidateToken((int) $result->id);

        $request->request->set('_target_path', $result->jumpTo);

        return $this->loginUser((string) $member->username, $request);
    }

    private function loginUser(string $username, Request $request): Response
    {
        try {
            $user = $this->userProvider->loadUserByUsername($username);
        } catch (UsernameNotFoundException $exception) {
            throw new PageNotFoundException('We don\'t know who you are :-(');
        }

        if (!$user instanceof FrontendUser) {
            throw new AccessDeniedException('Not a frontend user');
        }

        try {
            $this->userChecker->checkPreAuth($user);
            $this->userChecker->checkPostAuth($user);
        } catch (AccountStatusException $e) {
            // i.e. account disabled
            throw new AccessDeniedException('Authentication checks failed');
        }

        $usernamePasswordToken = new UsernamePasswordToken($user, null, 'frontend', $user->getRoles());
        $this->tokenStorage->setToken($usernamePasswordToken);
        $event = new InteractiveLoginEvent($request, $usernamePasswordToken);
        $this->dispatcher->dispatch($event);
        $this->logger->log(LogLevel::INFO, sprintf('User "%s" was logged in automatically', $username), [
            'contao' => new ContaoContext(__METHOD__, TL_ACCESS),
        ]);

        return $this->authenticationSuccessHandler->onAuthenticationSuccess($request, $usernamePasswordToken);
    }

    private function invalidateToken(int $tokenId): void
    {
        $this->connection->createQueryBuilder()
            ->delete('tl_member_login_token')
            ->where('id=:id')
            ->setParameter('id', $tokenId)
            ->execute()
        ;
    }
}
