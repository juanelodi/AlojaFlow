<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'documentos' => [
        'ine' => 'INE (documento eleitoral mexicano)',
        'pasaporte' => 'Passaporte',
        'licencia' => 'Carta de condução',
        'fmm' => 'Formulário migratório (FMM)',
        'dni' => 'DNI',
        'nie' => 'NIE',
        'identificacion' => 'Documento de identificação',
        'otro' => 'Outro',
    ],
    'campos' => [
        'procedencia' => 'Cidade de origem',
        'motivo_viaje' => 'Motivo da viagem',
        'placas' => 'Matrícula do veículo',
        'soporte' => 'Número de suporte',
        'parentesco' => 'Parentesco (para menores)',
    ],
    'generico' => [
        'aviso_privacidad' => [
            'titulo' => 'Aviso de privacidade',
            'cuerpo' => "{propiedad} ({domicilio}) é responsável pelos seus dados pessoais.\n\nUsamo-los para gerir a sua reserva e estadia, registá-lo como hóspede, cobrar e cumprir a lei. Partilhamo-los apenas com os fornecedores que nos ajudam a prestar o serviço e com as autoridades quando a lei o exige. Não os vendemos.\n\nA foto do seu documento é guardada até 30 dias após a sua saída; os dados de registo, um ano.\n\nPode aceder aos seus dados, corrigi-los ou pedir que os apaguemos escrevendo para {correo}.\n\nEste texto é uma base: deve ser adaptado à lei do seu país.",
        ],
        'contrato_grupo' => [
            'titulo' => 'Contrato de grupo',
            'cuerpo' => <<<'TEXTO'
Este contrato regula a estadia do grupo «{grupo}» ({codigo}) em {propiedad}, coordenado por {coordinador}.

1. ESTADIA. De {llegada} a {salida}, com {habitaciones} quartos.
2. PREÇO. O total é {total}. Pagamentos acordados:
{pagos}
3. LISTA DE HÓSPEDES. O coordenador dirá quem vai em cada quarto até {fecha_limite}.
4. CANCELAMENTO. Aplica-se a política de cancelamento comunicada.
5. CONDIÇÕES. O coordenador dá a conhecer ao grupo o regulamento do alojamento. Cada hóspede responde pelos danos que causar.
6. RECLAMAÇÕES. Escreve-nos para {correo}.
TEXTO,
        ],
        'contrato' => [
            'titulo' => 'Condições de alojamento',
            'cuerpo' => "Estas condições regem a sua estadia em {propiedad}.\n\n1. O preço é o indicado na sua reserva.\n2. A chegada e a saída fazem-se nos horários comunicados.\n3. Só podem ficar as pessoas registadas.\n4. Aplica-se a política de cancelamento comunicada na reserva.\n5. O hóspede responde pelos danos que causar.\n\nPara qualquer reclamação, escreva para {correo}.",
        ],
        'reglamento' => [
            'titulo' => 'Regulamento interno',
            'cuerpo' => 'Para o conforto de todos, em {propiedad} pedimos-lhe que respeite o descanso dos outros, não fume nos quartos, registe todas as pessoas que ficam e nos avise de qualquer dano.',
        ],
        'politica_cancelacion' => [
            'titulo' => 'Política de cancelamento',
            'cuerpo' => 'Pode cancelar sem custos até [número] dias antes da chegada. Depois será cobrado [percentagem] % do total. Se não comparecer, será cobrada a primeira noite. Para cancelar, escreva para {correo}.',
        ],
    ],
];
