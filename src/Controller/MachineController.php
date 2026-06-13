<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Machine;
use App\Service\FicheMachineService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Fiche publique d'une machine (BF_1.1).
 * Présente le « pourquoi du comment » : usages concrets, matériaux, atouts.
 * La donnée métier vient de l'entité ; le contenu éditorial du
 * FicheMachineService, indexé par type.
 */
class MachineController extends AbstractController
{
    #[Route('/machines/{id}', name: 'app_machine_fiche', methods: ['GET'])]
    public function fiche(Machine $machine, FicheMachineService $fiches): Response
    {
        return $this->render('machine/fiche.html.twig', [
            'machine' => $machine,
            'fiche' => $fiches->pourType($machine->getType()),
        ]);
    }
}
