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
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class TokenLogin extends Controller
{

    /**
     * @var UserProviderInterface
     */
    private $userProvider;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var UrlGenerator
     */
    private $router;

    /**
     * CreateNewUserListener constructor.
     *
     * @param UserProviderInterface $userProvider The user provider.
     * @param TokenStorageInterface $tokenStorage The token storage.
     * @param Session               $session      The session.
     * @param Connection            $connection   The database connection.
     * @param UrlGenerator          $router       The Contao url generator.
     */
    public function __construct(
        UserProviderInterface $userProvider,
        TokenStorageInterface $tokenStorage,
        Session $session,
        Connection $connection,
        UrlGenerator $router
    ) {
        $this->userProvider = $userProvider;
        $this->tokenStorage = $tokenStorage;
        $this->session      = $session;
        $this->connection   = $connection;
        $this->router       = $router;
    }

    /**
     * @param string  $token
     * @param Request $request
     *
     * @throws \InvalidArgumentException
     * @throws AccessDeniedException
     * @throws RedirectResponseException
     * @throws PageNotFoundException
     * @throws UsernameNotFoundException
     */
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
            throw new AccessDeniedException('Token not found or expired: '.$token);
        }

        $member = MemberModel::findByPk($result->member);
        if (null === $member) {
            throw new PageNotFoundException();
        }

        // Invalidate token
        $this->connection->createQueryBuilder()
            ->delete('tl_member_login_token')
            ->where('id=:id')
            ->setParameter('id', $result->id)
            ->execute();

        // Authenticate user
        $user = $this->userProvider->loadUserByUsername($member->username);

        $usernamePasswordToken = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->tokenStorage->setToken($usernamePasswordToken);
        $this->session->set('_security_main', serialize($usernamePasswordToken));

        $url = $this->router->generate($result->jumpTo ?: 'index');

        throw new RedirectResponseException($url);
    }
}
