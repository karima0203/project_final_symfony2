<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Repository\DetailRepository;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use App\Repository\ProduitRepository;
use App\Repository\TailleProduitRepository;
use Pagerfanta\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProduitsController extends AbstractController
{
    #[Route('/produits/categorie/{categoryId}', name: 'produits_par_categorie')]
    public function produitsParCategorie(ProduitRepository $produitRepository, Request $request, int $categoryId): Response
    {
        // Récupérer les produits avec category_id 
        $produitsQuery = $produitRepository->findBy(['category' => $categoryId]);
 

        $adapter = new ArrayAdapter($produitsQuery);// il fournit les données des produits à paginer
        $produits = new Pagerfanta($adapter);// gère la logique de pagination elle meme

        // Définir le nombre d'éléments par page
        $produits->setMaxPerPage(15);
        

        // Récupérer la page demandée depuis la requête
        $currentPage = $request->query->get('page', 1);
        

        $produits->setCurrentPage($currentPage);// Définit la page actuelle
       

        switch ($categoryId) {
            case '1':
                $phrase = 'Chez Nous vous trouvez un large choix de vetements pour un look exellent et attirant!';
                break;
            case '2':
                $phrase = 'Vous trouverez un large choix de Make Up pour une beauté impréssionnante !
                ';
                break;
            case '3':
                $phrase = 'Une large gamme de bijoux pour une beauté incontournable';
                break;
            case '4':
                $phrase = ' Des Montres Raffinées et Luxes spécialements pour Vous !';
                break;
            default:
                $phrase = null;
        }
        // donc si on fait dd($produits) on obtient les proprietes de la classe pagerFanta et le tableau d'objets de produits se trouvent dans la variable adapter 
    
        // Afficher la liste des produits dans le template Twig
        return $this->render('produits/produits.html.twig', [
            'produits' => $produits,
            'phrase' => $phrase,
            'categoryId' => $categoryId
        ]);
    }

    #[Route('/produit/{id}', name: 'afficher_produit')]
    public function afficherProduit(Produit $produit): Response
    {
    
        
        $categorie = $produit->getCategory();
        $nomProduit = strtolower($produit->getDescription());

        // On déclare les variables null pour éviter les erreurs dans les endroits où on en a pas besoin (exemple, maquillage);
        $nomTaille = null;
        $tabTaille = null;

        // Si le produit contient l'un des mots clés dans sa description, alors il rentre dans cette condition et rempli LE tableau tabTaille en fonction du produit et ajoute le style de taille (cm,mm) également en fonction du nom.
        if (str_contains($nomProduit, 'bague')) {
            $tabTaille = ['44', '46', '48', '50', '52', '54', '56', '58', '60', '62', '64'];
            $nomTaille = "mm";
        } else if (str_contains($nomProduit, 'collier')) {
            $tabTaille = ['35', '40', '45', '50', '55', '60'];
            $nomTaille = 'cm';
        } else if (str_contains($nomProduit, 'bracelet') || $categorie->getId() == 4) {
            $tabTaille = ['14', '15', '16', '17', '18'];
            $nomTaille = 'cm';
        } else if ($categorie->getId() == 1) {
            $tabTaille = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        }


        return $this->render('produits/detailsProduit.html.twig', [
            'produit' => $produit,
            'nomTaille' => $nomTaille,
            'tabTaille' => $tabTaille
        ]);
    }

    #[Route('/ajouter-au-panier/{id}', name: 'ajouter_au_panier')]
    public function ajouterAuPanier( ProduitRepository $produitRepository ,SessionInterface $session, Request $request,$id): JsonResponse
    {
       $produit=$produitRepository->find($id);
        // Récupérer le panier actuel depuis la session
        $panier = $session->get('panier', []);
        //  récupère tous les paramètres de la requête GET actuelle.
        $params = $request->query->all();

        $tailles = $params['taille'] ??[] ;// si  $params['taille'] existe et il est non null alos $tailles = $params['taille']
        // sinon  $tailles= []
        // dd($tailles);

        $isTailleUniqueAdded = false;
        // Ajouter le produit au panier
        if (is_array($tailles)) {
            foreach ($tailles as $taille) {
                if ($taille == "" && $produit->getCategory()->getId() !== 2) {
                    return new JsonResponse(['erreur_message' => "Veuillez choisir une taille pour ce produit, merci"]);
                } else {
                    if ($taille !== "")  {
                        $isAlreadyAddedTaille = false;
                        foreach ($panier as $item) {
                            if ($item['id'] == $produit->getId() && $item['taille'] == $taille) {
                                $isAlreadyAddedTaille = true;
                                // break;
                                return new JsonResponse(['erreur_message' => "Ce produit existe déjà dans le panier"]);
                            }
                        }
                        if($isAlreadyAddedTaille == false){
                            $panier[] = [
                                'id' => $produit->getId(),
                                'image' => $produit->getImage(),
                                'description' => $produit->getDescription(),
                                'prix' => $produit->getPrix(),
                                'taille' => $taille
                            ];
                        }
                       
                    } else {
                        // Vérifier si le produit sans taille n'est pas déjà présent dans le panier
                        $isAlreadyAddedMakeup = false;
                        foreach ($panier as $item) {
                            if ($item['id'] == $produit->getId() && $item['taille'] == "taille_unique") {
                                $isAlreadyAddedMakeup = true;
                                // break;
                                return new JsonResponse(['erreur_message' => "Ce produit existe déjà dans le panier"]);
                            }
                        }
                        if (!$isAlreadyAddedMakeup) {
                            $panier[] = [
                                'id' => $produit->getId(),
                                'image' => $produit->getImage(),
                                'description' => $produit->getDescription(),
                                'prix' => $produit->getPrix(),
                                'taille' => "taille_unique"
                            ];
                        }
                    }
                }
            }
        }



        // Mettre à jour le panier dans la session
        $session->set('panier', $panier);

        $nbArticles = count($session->get('panier'));

        $session->set('nbArticles', $nbArticles);


        $response = new JsonResponse(['nbArticles' => $nbArticles]);// c'est un objet JSON 

        return $response;
    }


    // #[Route('/test', name: 'test')]
    // public function bonjour (DetailRepository $detailRepository): Response{

    //     $s=$detailRepository->Statistique();
    //     dd($s);
    // }
   
}
