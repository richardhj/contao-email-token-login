<?php

/**
 * This file is part of richardhj/contao-email-token-login.
 *
 * Copyright (c) 2018-2018 Richard Henkenjohann
 *
 * @package   richardhj/contao-email-token-login
 * @author    Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright 2018-2018 Richard Henkenjohann
 * @license   https://github.com/richardhj/contao-email-token-login/blob/master/LICENSE
 */

namespace Richardhj\ContaoEmailTokenLoginBundle\Controller;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Routing\UrlGenerator;
use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class TokenLogin extends AbstractController
{

    private $userProvider;

    private $tokenStorage;

    private $connection;

    private $router;

    private $dispatcher;

    private $twig;

    private $translator;

    public function __construct(
        UserProviderInterface $userProvider,
        TokenStorageInterface $tokenStorage,
        Connection $connection,
        UrlGenerator $router,
        EventDispatcherInterface $dispatcher,
        TwigEnvironment $twig,
        TranslatorInterface $translator
    ) {
        $this->userProvider = $userProvider;
        $this->tokenStorage = $tokenStorage;
        $this->connection   = $connection;
        $this->router       = $router;
        $this->dispatcher   = $dispatcher;
        $this->twig         = $twig;
        $this->translator   = $translator;
    }

    public function __invoke(string $token, Request $request)
    {
        $statement = $this->connection->createQueryBuilder()
            ->select('t.id AS id', 't.member AS member', 'p.alias AS jumpTo')
            ->from('tl_member_login_token', 't')
            ->where('t.token =:token')
            ->andWhere('t.expires >=:time')
            ->leftJoin('t', 'tl_page', 'p', 't.jumpTo = p.id')
            ->setParameter('token', $token)
            ->setParameter('time', time())
            ->execute();

        $result = $statement->fetch(\PDO::FETCH_OBJ);
        if (false === $result) {
            throw new AccessDeniedException('Token not found or expired: ' . $token);
        }

        $member = MemberModel::findByPk($result->member);
        if (null === $member) {
            throw new PageNotFoundException('We don\'t know who you are :-(');
        }

        if (!$request->isMethod('POST')) {
            // Only proceed on POST requests. On GET, show a <form> to gather a POST request. See #3

            return Response::create(
                $this->twig->render(
                    '@RichardhjContaoEmailTokenLogin/login_entrypoint.html.twig',
                    [
                        'loginBT'       => $this->translator->trans('MSC.loginBT', [], 'contao_default'),
                        'form_id'       => 'login' . substr($token, 0, 4),
                        'form_action'   => $request->getRequestUri(),
                        'request_token' => REQUEST_TOKEN
                    ]
                )
            );
        }

        // Invalidate token
        $this->connection->createQueryBuilder()
            ->delete('tl_member_login_token')
            ->where('id=:id')
            ->setParameter('id', $result->id)
            ->execute();

        // Authenticate user
        try {
            $user = $this->userProvider->loadUserByUsername($member->username);
        } catch (UsernameNotFoundException $exception) {
            throw new PageNotFoundException('We don\'t know who you are :-(');
        }

        $usernamePasswordToken = new UsernamePasswordToken($user, null, 'frontend', $user->getRoles());
        $this->tokenStorage->setToken($usernamePasswordToken);

        $event = new InteractiveLoginEvent($request, $usernamePasswordToken);
        $this->dispatcher->dispatch($event);

        $url = $this->router->generate($result->jumpTo ?: 'index');

        throw new RedirectResponseException($url);
    }
}
