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

namespace Richardhj\ContaoEmailTokenLoginBundle\Module;


use Contao\BackendTemplate;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Module;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use NotificationCenter\Model\Notification;
use Patchwork\Utf8;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\Translation\TranslatorInterface;

class TokenLogin extends Module
{
    /**
     * @var TokenChecker
     */
    private $tokenChecker;

    /**
     * @var LogoutUrlGenerator
     */
    private $logoutUrlGenerator;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var AuthenticationUtils
     */
    private $authenticationUtils;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var TokenGeneratorInterface
     */
    private $tokenGenerator;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * TokenLogin constructor.
     *
     * @param ModuleModel $module
     * @param string      $column
     */
    public function __construct(ModuleModel $module, string $column)
    {
        parent::__construct($module, $column);

        $this->strTemplate = 'mod_login_email_token';

        $this->tokenChecker        = System::getContainer()->get('contao.security.token_checker');
        $this->logoutUrlGenerator  = System::getContainer()->get('security.logout_url_generator');
        $this->requestStack        = System::getContainer()->get('request_stack');
        $this->authenticationUtils = System::getContainer()->get('security.authentication_utils');
        $this->connection          = System::getContainer()->get('database_connection');
        $this->tokenGenerator      = System::getContainer()->get('security.csrf.token_generator');
        $this->router              = System::getContainer()->get('router');
        $this->translator          = System::getContainer()->get('translator');
    }

    /**
     * Display a login form
     *
     * @return string
     */
    public function generate(): string
    {
        if ('BE' === TL_MODE) {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.Utf8::strtoupper($this->translator->trans('FMD.login.0', [], 'contao_modules')).' ###';
            $objTemplate->title    = $this->headline;
            $objTemplate->id       = $this->id;
            $objTemplate->link     = $this->name;
            $objTemplate->href     = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

//        if (!$_POST && $this->redirectBack && ($strReferer = $this->getReferer()) != \Environment::get('request')) {
//            $_SESSION['LAST_PAGE_VISITED'] = $strReferer;
//        }

        return parent::generate();
    }


    /**
     * Generate the module
     *
     * @throws \Exception
     */
    protected function compile(): void
    {
        if ($this->tokenChecker->hasFrontendUser()) {
            /** @var PageModel $objPage */
            global $objPage;

            $strRedirect = \Environment::get('base').\Environment::get('request');

            // Redirect to last page visited
            if ($this->redirectBack && $_SESSION['LAST_PAGE_VISITED'] != '') {
                $strRedirect = \Environment::get('base').$_SESSION['LAST_PAGE_VISITED'];
            } // Redirect home if the page is protected
            elseif ($objPage->protected) {
                $strRedirect = \Environment::get('base');
            }

            $this->Template->logout     = true;
            $this->Template->formId     = 'tl_logout_'.$this->id;
            $this->Template->slabel     = \StringUtil::specialchars($this->translator->trans('MSC.logout', [], 'contao_default'));
            $this->Template->loggedInAs =
                sprintf($this->translator->trans('MSC.loggedInAs', [], 'contao_default'), FrontendUser::getInstance()->username);
            $this->Template->action     = $this->logoutUrlGenerator->getLogoutPath();
            $this->Template->targetPath = \StringUtil::specialchars($strRedirect);

            if (FrontendUser::getInstance()->lastLogin > 0) {
                $this->Template->lastLogin = sprintf(
                    $this->translator->trans('MSC.lastLogin.1', [], 'contao_default'),
                    \Date::parse($objPage->datimFormat, FrontendUser::getInstance()->lastLogin)
                );
            }

            return;
        }

        $request = $this->requestStack->getMasterRequest();

        if (0 !== $request->request->count()) {


            $member = MemberModel::findByUsername($request->request->get('username'));
            if (null === $member) {
                $this->Template->hasError = true;
                $this->Template->message  = $this->translator->trans('ERR.invalidLogin', [], 'contao_default');;
            } else {

//            $blnRedirectBack = false;
//
//            // Redirect to the last page visited
//            if ($this->redirectBack && $_SESSION['LAST_PAGE_VISITED'] != '') {
//                $blnRedirectBack = true;
//                $strRedirect     = $_SESSION['LAST_PAGE_VISITED'];
//            } // Redirect to the jumpTo page
//            elseif ($this->jumpTo && ($objTarget = $this->objModel->getRelated('jumpTo')) instanceof PageModel) {
                $objTarget = $this->objModel->getRelated('jumpTo');
                $jumpTo    = ($objTarget instanceof PageModel) ? $objTarget->id : 0;
//            }


                // Generate token
                $token = $this->tokenGenerator->generateToken();
                $this->connection->createQueryBuilder()
                    ->insert('tl_member_login_token')
                    ->values(
                        [
                            'tstamp'  => '?',
                            'expires' => '?',
                            'member'  => '?',
                            'token'   => '?',
                            'jumpTo'  => '?',
                        ]
                    )
                    ->setParameter(0, time())
                    ->setParameter(1, strtotime('+2 hours'))
                    ->setParameter(2, $member->id)
                    ->setParameter(3, $token)
                    ->setParameter(4, $jumpTo)
                    ->execute();

                // Send notification
                $notificationTokens = [
                    'recipient_email' => $member->email,
                    'domain'          => $request->getHost(),
                    'link'            => $this->router->generate(
                        'richardhj.contao_email_token_login.token_login',
                        ['token' => $token],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                ];
                foreach ($member->row() as $field => $value) {
                    $notificationTokens['member_'.$field] = $value;
                }

                $notification = Notification::findByPk($this->nc_notification);
                if (null !== $notification) {
                    $notification->send($notificationTokens);

                    $this->Template->doNotShowForm = true;
                    $this->Template->message       =
                        $this->translator->trans('MSC.token_login.form_success', [], 'contao_default');
                } else {
                    $this->Template->hasError = true;
                    $this->Template->message  =
                        $this->translator->trans('MSC.token_login.form_error', [], 'contao_default');
                }
            }
        }

        $this->Template->username = $this->translator->trans('MSC.username', [], 'contao_default');
        $this->Template->action   = $request->getRequestUri();
        $this->Template->slabel   = StringUtil::specialchars($this->translator->trans('MSC.login', [], 'contao_default'));
        $this->Template->value    = StringUtil::specialchars($this->authenticationUtils->getLastUsername());
        $this->Template->formId   = 'tl_login_'.$this->id;
    }
}
