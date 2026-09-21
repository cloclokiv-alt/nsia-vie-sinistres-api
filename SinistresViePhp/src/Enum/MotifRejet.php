<?php

namespace App\Enum;

/**
 * Motif d'un refus de garantie ou d'un classement.
 *
 * Un rejet est toujours motive : l'assure peut le contester, et le motif doit pouvoir
 * etre oppose devant le regulateur.
 */
enum MotifRejet: string
{
    case HorsGarantie = 'Événement non couvert';
    case ExclusionContractuelle = 'Exclusion contractuelle';
    case DelaiCarence = 'Délai de carence';
    case FausseDeclaration = 'Fausse déclaration';
    case Prescription = 'Action prescrite';
    case ContratSansEffet = 'Contrat sans effet';
    case PrimesImpayees = 'Primes impayées';
    case BeneficiaireNonIdentifie = 'Bénéficiaire non identifié';
    case SinistreNonJustifie = 'Sinistre non justifié';
    case Renonciation = 'Renonciation du bénéficiaire';

    /**
     * Motifs qui classent le dossier sans suite plutot que de le rejeter : il n'y a
     * pas refus de garantie, le dossier s'eteint de lui-meme.
     *
     * @return list<self>
     */
    public static function motifsDeClassement(): array
    {
        return [self::Prescription, self::BeneficiaireNonIdentifie, self::Renonciation];
    }

    public function classeSansSuite(): bool
    {
        return in_array($this, self::motifsDeClassement(), true);
    }
}
