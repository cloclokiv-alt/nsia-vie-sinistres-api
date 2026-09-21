<?php

namespace App\Enums;

/**
 * Un rejet est toujours motivé : l'assuré peut le contester, et le motif
 * doit pouvoir être opposé devant le régulateur.
 */
enum MotifRejet: string
{
    case HorsGarantie = 'hors_garantie';
    case ExclusionContractuelle = 'exclusion_contractuelle';
    case DelaiCarence = 'delai_carence';
    case FausseDeclaration = 'fausse_declaration';
    case Prescription = 'prescription';
    case ContratSansEffet = 'contrat_sans_effet';
    case PrimesImpayees = 'primes_impayees';
    case BeneficiaireNonIdentifie = 'beneficiaire_non_identifie';
    case SinistreNonJustifie = 'sinistre_non_justifie';
    case Renonciation = 'renonciation';

    public function label(): string
    {
        return match ($this) {
            self::HorsGarantie => 'Événement non couvert par la garantie',
            self::ExclusionContractuelle => 'Exclusion prévue au contrat',
            self::DelaiCarence => 'Sinistre survenu pendant le délai de carence',
            self::FausseDeclaration => 'Fausse déclaration à la souscription',
            self::Prescription => 'Action prescrite',
            self::ContratSansEffet => 'Contrat sans effet au jour du sinistre',
            self::PrimesImpayees => 'Primes impayées',
            self::BeneficiaireNonIdentifie => 'Bénéficiaire non identifié',
            self::SinistreNonJustifie => 'Sinistre non justifié',
            self::Renonciation => 'Renonciation du bénéficiaire',
        };
    }

    /**
     * Les motifs qui classent le dossier sans suite plutôt que de le rejeter :
     * il n'y a pas de refus de garantie, le dossier s'éteint de lui-même.
     *
     * @return array<int, self>
     */
    public static function motifsDeClassement(): array
    {
        return [self::Prescription, self::BeneficiaireNonIdentifie, self::Renonciation];
    }
}
