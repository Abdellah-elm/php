<?php

namespace App\Controller;

use App\Entity\Billet;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface; 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BookingController extends AbstractController
{
    #[Route('/booking/add/{id}', name: 'app_booking_add')]
    public function add(Event $event, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('danger', 'Vous devez être connecté pour réserver.');
            return $this->redirectToRoute('app_login');
        }

        $ticketsSold = $event->getBillets()->count();
        if ($ticketsSold >= $event->getCapacity()) {
            $this->addFlash('danger', 'Désolé, cet événement est complet !');
            return $this->redirectToRoute('app_event_index');
        }

        $billet = new Billet();
        $billet->setEvent($event);
        $billet->setUser($user);
        $billet->setStatus('Confirmé');

        $entityManager->persist($billet);
        $entityManager->flush();

        $this->addFlash('success', 'Félicitations ! Place réservée pour ' . $event->getTitle());
        
        return $this->redirectToRoute('app_my_tickets');
    }

    #[Route('/my-tickets', name: 'app_my_tickets')]
    public function myTickets(): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        return $this->render('booking/my_tickets.html.twig', [
            'billets' => $user->getBillets(),
        ]);
    }

    #[Route('/billet/download/{id}', name: 'app_billet_pdf')]
    #[IsGranted('ROLE_USER')]
    public function generatePdf(Billet $billet, UrlGeneratorInterface $urlGenerator): Response
    {
        $user = $this->getUser();
        
        if ($billet->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
             throw $this->createAccessDeniedException('Accès refusé à ce billet.');
        }

        $qrContent = $urlGenerator->generate(
            'app_validate_ticket', 
            ['id' => $billet->getId()], 
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $writer = new PngWriter();

        $qrCode = new QrCode(
            data: $qrContent,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 150,
            margin: 5
        );

        $result = $writer->write($qrCode);
        $qrCodeDataUri = $result->getDataUri();


        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('booking/pdf_ticket.html.twig', [
            'billet' => $billet,
            'event'  => $billet->getEvent(),
            'user'   => $user,
            'qrCode' => $qrCodeDataUri 
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="billet-'.$billet->getId().'.pdf"',
        ]);
    }
}