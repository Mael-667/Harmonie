<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home_index')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
        ]);
    }

    #[Route('/rgpd', name: 'home_rgpd')]
    public function rgpd(): Response
    {
        return $this->render('home/rgpd.html.twig', [
        ]);
    }

    #[Route('/mentions-legales', name: 'home_mentions_legales')]
    public function mentionsLegales(): Response
    {
        return $this->render('home/mentions-legales.html.twig', [
        ]);
    }

    #[Route('/cgu', name: 'home_cgu')]
    public function cgu(): Response
    {
        return $this->render('home/cgu.html.twig', [
        ]);
    }
}
