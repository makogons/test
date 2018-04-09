<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Entity\User;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class UserController extends Controller
{
    public function index()
    {
        $users = $this->getDoctrine()->getRepository(User::class)->findAll();

        return $this->render('index.twig', ['users' => $users]);
    }

    public function create()
    {
        $formFactory = Forms::createFormFactory();

        $form = $formFactory->createBuilder()
            ->add('email', EmailType::class)
            ->add('name', TextType::class)
            ->getForm();

        $data = Request::createFromGlobals()->get('form');

        if ($data) {
            $form->submit($data);
            if (strlen($data['name']) < 3) {
                $form->addError(new FormError('name is too short!'));
                return $this->redirect('/create');
            }

            if ($form->isValid()) {
                $user = new User();
                $user->setEmail($data['email']);
                $user->setName($data['name']);

                $entityManager = $this->getDoctrine()->getManager();

                if (isset($data['friends'])) {
                    foreach ($data['friends'] as $friend) {
                        $u = $this->getDoctrine()->getRepository(User::class)->findOneBy(['id' => $friend]);
                        $u->getFriends()->add($user);
                        $user->getFriends()->add($u);
                    }
                }

                $entityManager->persist($user);
                $entityManager->flush();

                return $this->redirect('/');
            }

            $users = $this->getDoctrine()->getRepository(User::class)->findAll();
            return $this->render('view.twig', ['form' => $form->createView(), 'users' => $users, 'friends' => [], 'userId' => null]);
        }

        $users = $this->getDoctrine()->getRepository(User::class)->findAll();
        return $this->render('view.twig', ['form' => $form->createView(), 'users' => $users, 'friends' => [], 'userId' => null]);
    }

    public function edit()
    {
        $formFactory = Forms::createFormFactory();

        $data = Request::createFromGlobals()->get('form');
        $userId = isset($data['id']) ? $data['id'] : Request::createFromGlobals()->get('id');

        if (!$userId) {
            throw new BadRequestHttpException('id must be specified!');
        }

        /**
         * @var User $user
         */
        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['id' => $userId]);

        if (!$user) {
            throw new NotFoundHttpException('User not found!');
        }

        $form = $formFactory->createBuilder()
            ->add('email', EmailType::class, ['data' => $user->getEmail()])
            ->add('name', TextType::class, ['data' => $user->getName()])
            ->add('id', HiddenType::class, ['data' => $userId])
            ->getForm();

        if ($data) {
            $entityManager = $this->getDoctrine()->getManager();

            $form->submit($data);

            if (strlen($data['name']) < 3) {
                $form->addError(new FormError('name is too short!'));
            }

            if ($form->isValid()) {
                $user->setEmail($data['email']);
                $user->setName($data['name']);

                $statement = $entityManager->getConnection()->prepare('DELETE FROM `friends` WHERE user_id = :user_id OR friend_user_id = :user_id');
                $statement->bindValue('user_id', $user->getId());
                $statement->execute();

                if (isset($data['friends'])) {
                    foreach ($data['friends'] as $friend) {
                        /**
                         * @var User $u
                         */
                        $u = $this->getDoctrine()->getRepository(User::class)->findOneBy(['id' => $friend]);
                        $u->getFriends()->add($user);
                        $user->getFriends()->add($u);
                    }
                }

                $entityManager->flush();

                return $this->redirect('/');
            } else {
                $friendIds = [];

                foreach ($user->getFriends() as $friend) {
                    $friendIds[] = $friend->getId();
                }

                $users = $this->getDoctrine()->getRepository(User::class)->findAll();

                return $this->render('view.twig', ['form' => $form->createView(), 'users' => $users, 'friends' => $friendIds, 'userId' => $userId]);
            }
        }

        $friendIds = [];

        foreach ($user->getFriends() as $friend) {
            $friendIds[] = $friend->getId();
        }

        $users = $this->getDoctrine()->getRepository(User::class)->findAll();

        return $this->render('view.twig', ['form' => $form->createView(), 'users' => $users, 'friends' => $friendIds, 'userId' => $userId]);
    }

    public function delete()
    {
        $userId = Request::createFromGlobals()->get('id');

        if (!$userId) {
            throw new BadRequestHttpException('id must be specified!');
        }
        /**
         * @var User $user
         */
        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['id' => $userId]);

        if (!$user) {
            throw new NotFoundHttpException('User not found!');
        }

        foreach ($user->getFriends() as $friend) {
            $keys = $user->getFriends()->getKeys();
            foreach ($keys as $key) {
                $user->getFriends()->remove($key);
            }
        }

        $entityManager = $this->getDoctrine()->getManager();

        $entityManager->remove($user);

        $entityManager->flush();

        return $this->redirect('/');
    }
}