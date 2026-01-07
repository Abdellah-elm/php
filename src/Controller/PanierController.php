<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Panier;
use App\Repository\PanierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/panier')]
#[IsGranted('ROLE_USER')] 
final class PanierController extends AbstractController
{
    #[Route('/', name: 'app_panier_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        
        return $this->render('panier/index.html.twig', [
            'items' => $user->getPaniers(), 
        ]);
    }

    #[Route('/add/{id}', name: 'app_panier_add', methods: ['GET'])]
    public function add(Event $event, EntityManagerInterface $em, PanierRepository $panierRepo): Response
    {
        $user = $this->getUser();

        $panierItem = $panierRepo->findOneBy(['user' => $user, 'event' => $event]);

        if ($panierItem) {
            $panierItem->setQuantite($panierItem->getQuantite() + 1);
        } else {
            $panierItem = new Panier();
            $panierItem->setUser($user);
            $panierItem->setEvent($event);
            $panierItem->setQuantite(1);
            $em->persist($panierItem);
        }

        $em->flush(); 

        $this->addFlash('success', 'Article ajouté au panier !');
        
        return $this->redirectToRoute('app_panier_index'); 
    }

    #[Route('/delete/{id}', name: 'app_panier_delete', methods: ['GET'])]
    public function delete(Panier $panier, EntityManagerInterface $em): Response
    {
        if ($panier->getUser() === $this->getUser()) {
            $em->remove($panier);
            $em->flush();
            $this->addFlash('info', 'Article retiré du panier.');
        }

        return $this->redirectToRoute('app_panier_index');
    }
}