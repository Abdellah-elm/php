<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commande')]
#[IsGranted('ROLE_USER')] 
final class CommandeController extends AbstractController
{
    
    #[Route('/', name: 'app_commande_index', methods: ['GET'])]
    public function index(): Response
    {
        
        $user = $this->getUser();
        
        return $this->render('commande/index.html.twig', [
            'commandes' => $user->getCommandes(),
        ]);
    }

    #[Route('/valider', name: 'app_commande_validate', methods: ['GET'])]
    public function validate(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $panierItems = $user->getPaniers(); 

        if ($panierItems->isEmpty()) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_event_index');
        }


        $commande = new Commande();
        $commande->setUser($user);
        $commande->setDateCommande(new \DateTime()); 
        $commande->setStatut('Payée'); 
        
        $em->persist($commande);

        foreach ($panierItems as $item) {
            
            for ($i = 0; $i < $item->getQuantite(); $i++) {
                $billet = new Billet();
                $billet->setUser($user);
                $billet->setEvent($item->getEvent());
                $billet->setCommande($commande); 
                $billet->setStatus('Valide');
                
                $em->persist($billet);
            }

            $em->remove($item);
        }

        $em->flush();

        $this->addFlash('success', 'Votre commande a été validée avec succès ! Vos billets sont disponibles.');
        
        return $this->redirectToRoute('app_my_tickets');
    }

    #[Route('/{id}', name: 'app_commande_show', methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        if ($commande->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir cette commande.');
        }

        return $this->render('commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }
}