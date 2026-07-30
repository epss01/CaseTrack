<?php

namespace Database\Factories\Support;

/**
 * Curated Filipino name pools for demo and test data.
 *
 * Faker has no Filipino person provider — its en_PH locale localises
 * addresses and phone numbers but falls back to English names — so the pools
 * are kept here rather than generated.
 *
 * These are ordinary common names, deliberately not modelled on any real
 * person. Seeded records are marked as demo data by their DEMO- docket
 * numbers, not by the names.
 */
class PhilippineNames
{
    /** @var list<string> */
    private const FIRST_NAMES = [
        'Ana Marie', 'Rogelio', 'Cristina', 'Ferdinand', 'Maribel',
        'Josefa', 'Elena', 'Ramon', 'Teresita', 'Ernesto',
        'Lourdes', 'Danilo', 'Imelda', 'Corazon', 'Benigno',
        'Leticia', 'Arturo', 'Marilou', 'Edgardo', 'Norma',
        'Reynaldo', 'Perlita', 'Alfredo', 'Divina', 'Nestor',
        'Rosalinda', 'Wilfredo', 'Milagros', 'Carlito', 'Erlinda',
        'Bienvenido', 'Aurora', 'Salvador', 'Remedios', 'Efren',
        'Consuelo', 'Rolando', 'Editha', 'Virgilio', 'Purificacion',
    ];

    /**
     * Deliberately excludes the surnames DemoDataSeeder gives its staff
     * (Bautista, Tan, Lim, Ocampo, Serrano, Alonzo, Padilla) so a victim or
     * respondent can never end up sharing a full name with an investigator
     * or supervisor, which reads as a bug during a demo.
     *
     * @var list<string>
     */
    private const LAST_NAMES = [
        'Katigbak', 'Sumulong', 'Panganiban', 'Buenaventura', 'Lacsamana',
        'Ramos', 'Villanueva', 'Recto', 'Balingit', 'Dela Cruz',
        'Mendoza', 'Aquino', 'Cabrera', 'Delgado', 'Espinosa',
        'Fernandez', 'Gonzales', 'Hidalgo', 'Ilagan', 'Jimenez',
        'Lagman', 'Macaraig', 'Nolasco', 'Obispo', 'Pascual',
        'Reyes', 'Salazar', 'Tolentino', 'Uy', 'Valdez',
        'Ybanez', 'Zamora', 'Abad', 'Cortez', 'Diaz',
        'Enriquez', 'Bacolod', 'Quiambao', 'Sarmiento', 'Fuentes',
    ];

    /**
     * Ranks by the service that actually uses them.
     *
     * Kept agency-by-agency rather than in one pool so a respondent never
     * comes out as, say, a jail-officer rank filed under the PNP — the sort
     * of thing the office would notice immediately in a demo.
     *
     * @var array<string, list<string>>
     */
    private const RANKS_BY_AGENCY = [
        'PNP' => ['Pat', 'PCpl', 'PSSg', 'PMSg', 'PSMS', 'PLT', 'PCPT'],
        'BJMP' => ['JO1', 'JO2', 'JO3', 'SJO1', 'SJO2'],
        'BFP' => ['FO1', 'FO2', 'FO3', 'SFO1'],
        'AFP' => ['Pvt', 'PFC', 'Cpl.', 'Sgt.', 'SSgt.', '2Lt.', '1Lt.', 'Capt.'],
        'NBI' => ['SI I', 'SI II', 'SI III', 'Agent'],
    ];

    /**
     * Titles for respondents who are officials rather than officers.
     *
     * All role-based and gender-neutral: Mr./Ms. would have to agree with the
     * first name, and the pools are not gendered. An empty entry covers
     * respondents who hold no office at all.
     *
     * @var list<string>
     */
    private const CIVILIAN_TITLES = [
        'Brgy. Capt.', 'Brgy. Kag.', 'Brgy. Tanod', 'Councilor', '',
    ];

    /**
     * An ordinary person: complainants, victims, staff.
     */
    public static function person(): string
    {
        return fake()->randomElement(self::FIRST_NAMES).' '.fake()->randomElement(self::LAST_NAMES);
    }

    public static function firstName(): string
    {
        return fake()->randomElement(self::FIRST_NAMES);
    }

    public static function lastName(): string
    {
        return fake()->randomElement(self::LAST_NAMES);
    }

    /**
     * The uniformed services a respondent may be drawn from.
     *
     * @return list<string>
     */
    public static function uniformedAgencies(): array
    {
        return array_keys(self::RANKS_BY_AGENCY);
    }

    /**
     * A respondent from the given service, named with a rank that service
     * actually uses.
     */
    public static function officerOf(string $agency): string
    {
        return fake()->randomElement(self::RANKS_BY_AGENCY[$agency]).' '.self::person();
    }

    /**
     * A respondent who is a local official or a private individual.
     */
    public static function civilianRespondent(): string
    {
        return trim(fake()->randomElement(self::CIVILIAN_TITLES).' '.self::person());
    }
}
