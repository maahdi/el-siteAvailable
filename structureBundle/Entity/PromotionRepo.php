<?php

namespace EuroLiterie\structureBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * PromotionRepo
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PromotionRepo extends EntityRepository
{
    public function getHtml()
    {
        return '<div class="admin-c prom border">
                    <input type="hidden" name="id" value="%id%">
                    <section class="contentPromo">
                        <article class="adminPromo">
                            <label>Date de début :</label><input class="datepickerDebut" type="text" name="dateDebut" value="%dateDebut%"/>
                            <label>Date de fin :</label><input class="datepickerFin" type="text" name="dateFin" value="%dateFin%"/>
                            <label>Entête :</label><input class="PromoDesc" name="PromoDesc" value="%PromoDesc%"/>
                        </article>
                        <article class="adminPromoInfo">
                            <label>Description :</label><textarea class="textareaPromo" name="promoInfo">%promoInfo%</textarea>
                        </article>
                    </section>
                    <section class="btn-adminPanel">
                        <article class="btn-admin maj">
                            <li>Mettre à jour</li>
                        </article>
                        <article class="btn-admin sup">
                            <li>Supprimer</li>
                        </article>
                    </section>
                </div>';
    }

    public function findAll()
    {
        $query = $this->getEntityManager()->createQuery('select p from EuroLiteriestructureBundle:Promotion p order by p.dateDebut asc');
        return $query->getResult();
    }

    public function getNew()
    {
        $promo = new Promotion ();
        $promo->setTag('periode');
        $promo->setDateDebut(new \Datetime());
        $promo->setDateFin(new \Datetime('+7 days'));
        $promo->setPromoInfo('Informations complémentaires');
        $promo->setPromoDesc('Description de la promotion');
        $em = $this->getEntityManager();
        $em->persist($promo);
        $em->flush();
        $sql = 'select p from EuroLiteriestructureBundle:Promotion p where p.id = (select max(m.id) from EuroLiteriestructureBundle:Promotion m)';
        return $em->createQuery($sql)->getSingleResult();
    }
}
