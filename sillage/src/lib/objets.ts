import { getCollection, type CollectionEntry } from 'astro:content';

export type Objet = CollectionEntry<'objets'>;

export async function getObjetsDisponibles(): Promise<Objet[]> {
  const objets = await getCollection('objets', ({ data }) => data.statut !== 'archivee');
  return objets.sort((a, b) => a.data.ordre - b.data.ordre);
}

export async function getObjetsArchives(): Promise<Objet[]> {
  const objets = await getCollection('objets', ({ data }) => data.statut === 'archivee');
  return objets.sort((a, b) => {
    const dateA = a.data.dateVente ?? a.data.dateEntree;
    const dateB = b.data.dateVente ?? b.data.dateEntree;
    return dateB.getTime() - dateA.getTime();
  });
}

export async function getObjetsMisEnAvant(): Promise<Objet[]> {
  const objets = await getObjetsDisponibles();
  return objets.filter((o) => o.data.misEnAvant);
}

export function formatPrix(prix: number | undefined, devise: string): string {
  if (prix === undefined) return 'Sur demande';
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: devise,
    maximumFractionDigits: 0,
  }).format(prix);
}

export function libelleStatut(statut: Objet['data']['statut']): string {
  switch (statut) {
    case 'disponible':
      return 'Disponible';
    case 'reservee':
      return 'Réservée';
    case 'archivee':
      return 'Trouvé preneur';
  }
}
