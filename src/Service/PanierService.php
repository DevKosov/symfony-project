<?php
namespace App\Service;
use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;

// Service pour manipuler le panier et le stocker en session
class PanierService {

    const PANIER_SESSION = "panier";    // Le nom de la variable de session du panier
    private $session;                   // Le service Session
    private $productRepository;
    private $panier;                    // Tableau associatif idProduit => valeur ['produit','quantite']

    // constructeur du service
    public function __construct(SessionInterface $session, ProductRepository $productRepository) {
        $this->session = $session;
        $this->productRepository = $productRepository;

        if ($this->session->has(PanierService::PANIER_SESSION))
            $this->panier = $this->session->get(PanierService::PANIER_SESSION);
        else {
            $this->panier = [];
            $this->panier = $this->session->set(PanierService::PANIER_SESSION,$this->panier);
        }
    }

    // getContenu renvoie le contenu du panier
    // Tableau d'éléments [ "produit" => un produit, "quantite" => quantite ]
    public function getContenu() {
        $panier = [];
        foreach ($this->panier as $product){
            $produit = $this->productRepository->findBy(['id'=> $product['id_produit']]);
            $panier[] = [
                'produit' => $produit,
                'quantite' => $product['quantite']
            ];
        }
        return $panier;

    }

    // getTotal renvoie le montant total du panier
    public function getTotal() {
        if (empty($this->panier))
            return 0;
        else{
            $total = 0;
            foreach ($this->panier as $produit){
                $docProduit = $this->productRepository->findOneBy(['id'=> $produit['id_produit']]);
                if ($docProduit){

                    dump($docProduit);
                    $total += $docProduit->getPrix() * $produit['quantite'];
                }
                else
                    $total = 0;
            }
            return $total;
        }
    }

    // getNbProduits renvoie le nombre de produits dans le panier
    public function getNbProduits() {
        if (empty($this->panier))
            return 0;
        else{
            $total = 0;
            foreach ($this->panier as $produit){
                $total += $produit['quantite'];
            }
            return $total;
        }
    }

    // ajouterProduit ajoute au panier le produit $idProduit en quantite $quantite
    public function ajouterProduit(int $idProduit, int $quantite = 1) {

        if (isset($this->panier[$idProduit]))
            $this->panier[$idProduit]['quantite'] += $quantite;
        else {
            $this->panier[$idProduit] = [
                'id_produit' => $idProduit,
                'quantite' => $quantite
            ];
        }

        $this->session->set(PanierService::PANIER_SESSION,$this->panier);
    }

    // enleverProduit enlève du panier le produit $idProduit en quantite $quantite
    public function enleverProduit(int $idProduit, int $quantite = 1) {

        if (isset($this->panier[$idProduit])) {

            if ($quantite >= $this->panier[$idProduit]['quantite'])
                $this->supprimerProduit($idProduit);
            else
                $this->panier[$idProduit]['quantite'] -= $quantite;

            $this->session->set(PanierService::PANIER_SESSION,$this->panier);
        }
    }

    // supprimerProduit supprime complètement le produit $idProduit du panier
    public function supprimerProduit(int $idProduit) {
        if (isset($this->panier[$idProduit])) {
            unset($this->panier[$idProduit]);
            $this->session->set(PanierService::PANIER_SESSION, $this->panier);
        }
    }

    // vider vide complètement le panier
    public function vider()
    {
        $this->panier = [];
        $this->session->set(PanierService::PANIER_SESSION,$this->panier);
    }

    /**
     * Retourne la commande qui a ete cree
     * @param User $user
     * @return Commande
     */
    public function panierToCommande(UserInterface $user, EntityManagerInterface $entityManager) : Commande
    {
        $commandeEm = $entityManager->getRepository(Commande::class);
        $ligneDeCommandeEm = $entityManager->getRepository(LigneCommande::class);

        // Creation d'une commande
        $commande = new Commande();
        $commande->setUser($user);
        $commande->setStatut( 'En Cours');
        $entityManager->persist($commande);
        // Pour chaque ligne de commande sur le panier ajouter sur la commande
        foreach($this->getContenu() as $lcmd){
            dump($lcmd['produit'][0]);

            $ligneDeCommande = new LigneCommande();
            $ligneDeCommande->setCommande($commande);
            $ligneDeCommande->setProduct($lcmd['produit'][0]);
            $ligneDeCommande->setQuantite($lcmd['quantite']);
            $ligneDeCommande->setPrix($lcmd['produit'][0]->getPrix() * $lcmd['quantite']);
            $entityManager->persist($ligneDeCommande);
        }

        // flush

        $entityManager->flush();

        //Supprimer le panier
        $this->vider();

        return $commande;
    }
}