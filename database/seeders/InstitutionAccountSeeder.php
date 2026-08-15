<?php

namespace Database\Seeders;

use App\Models\InstitutionAccount;
use Illuminate\Database\Seeder;

class InstitutionAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Rows are [stud_id, stud_lname, stud_fname, stud_mname, stud_year].
        $students = [
            // BSCS 3
            ['243242', 'Abugatal', 'Hearty', 'L', 3],
            ['243238', 'Adamos', 'Rollencenel', 'A', 3],
            ['243063', 'Angcog', 'Athea Jean', 'P', 3],
            ['244944', 'Arañez', 'Cristan', 'A', 3],
            ['241992', 'Arapon', 'Geric', 'M', 3],
            ['242449', 'Betacura', 'Rico', 'M', 3],
            ['248865', 'Cabrera', 'Carl Eduard', 'T', 3],
            ['244028', 'Cagadas', 'Heart', 'Q', 3],
            ['241420', 'Campos', 'Anjelyn', 'Q', 3],
            ['245723', 'Cuajotor', 'Sam Ervin', 'L', 3],
            ['241336', 'Cuizon', 'Khert', 'G', 3],
            ['245909', 'Dela Vega', 'Mike Rynzo', 'L', 3],
            ['244395', 'Dumandagan', 'Ace', 'F', 3],
            ['243340', 'Gallego', 'Aljhun', 'A', 3],
            ['238045', 'Lobido', 'Rolando', 'F', 3],
            ['248380', 'Luna', 'Stephen Dave', 'V', 3],

            // BSCS 4
            ['237958', 'Abelgas', 'Hyacinth Claire', 'T', 4],
            ['232038', 'Adala', 'Filjoy', 'A', 4],
            ['232874', 'Adolfo', 'Jovett Joash', 'S', 4],
            ['233016', 'Avila', 'Harod Jay', 'T', 4],
            ['258577', 'Barrientos', 'Joshua', 'M', 4],
            ['238380', 'Broñola', 'Kenley', 'C', 4],
            ['239134', 'Caralos', 'Christian', 'D', 4],
            ['231814', 'Chiu', 'Abraham', 'M', 4],
            ['237689', 'Crusio', 'Johnryl', 'F', 4],
            ['234653', 'Daniel', 'Mary Joy', 'E', 4],
            ['233414', 'Eslit', 'Melanie', 'E', 4],
            ['234439', 'Guhitia', 'Jeah', 'T', 4],
            ['235821', 'Jambo', 'Jason Mark', 'F', 4],
            ['181931', 'Liwagon', 'Ivan', 'C', 4],
            ['232923', 'Loberanes', 'John Lloyd', 'D', 4],
            ['231943', 'Lumayag', 'Romilie', 'B', 4],
            ['236107', 'Magsayo', 'Kimberly', 'N', 4],
            ['239644', 'Malimit', 'Stella Mariz', 'A', 4],
            ['238344', 'Murallon', 'Millicent John', 'E', 4],
            ['233077', 'Ondona', 'Romel', 'G', 4],
            ['23211', 'Oniot', 'Romeo Jr.', 'D', 4],
            ['236775', 'Onto', 'Ronn Nathaniel', 'M', 4],
            ['234830', 'Pamaylaon', 'Jarsil John', 'P', 4],
            ['233388', 'Patan', 'Reycalyn', 'D', 4],
            ['235375', 'Pati-An', 'Vincent Jay', 'G', 4],
            ['231284', 'Quilo', 'Manilyn', 'E', 4],
            ['238660', 'Quirog', 'Ivan Jhon', 'D', 4],
            ['234777', 'Ramayrat', 'John Wayne', 'D', 4],
            ['194056', 'Abadilla', 'Ellcarl', 'B', 4],

            // BSCS 2
            ['252499', 'Antolijao', 'Juhan', 'O', 2],
            ['255422', 'Apao', 'Nylvia', 'M', 2],
            ['256169', 'Aranas', 'Vince Charl', 'J', 2],
            ['254369', 'Baguio', 'Allan', 'S', 2],
            ['257220', 'Bartolo', 'Juliemar', 'E', 2],
            ['252349', 'Dag-Uman', 'Junna', 'S', 2],
            ['256370', 'Derigay', 'Dunavan Kyle', 'M', 2],
            ['253750', 'Gementiza', 'John Alrey', 'D', 2],
            ['259691', 'Geografo', 'Angel Lou', 'B', 2],
            ['241821', 'Gonzaga', 'Blessy', 'C', 2],
            ['241736', 'Jone', 'Enrique Jr.', 'P', 2],
            ['255787', 'Jutag', 'Archie', 'B', 2],
            ['255193', 'Labador', 'Bal Gestly', 'P', 2],
            ['262652', 'Molde', 'Eirich Dianne', 'M', 2],
            ['254112', 'Morales', 'Kevin Jay', 'R', 2],
            ['254156', 'Palangan', 'Lucille Mae', 'G', 2],
            ['246282', 'Pugado', 'Elaura', 'F', 2],
            ['256518', 'Rodrigo', 'Nathaniel', 'A', 2],
            ['252428', 'Rubio', 'Krizia Nicole', 'M', 2],
            ['256546', 'Alandroque', 'Ashley', null, 2],
            ['253972', 'Amores', 'Chistean Joice', 'C', 2],
            ['253782', 'Aplaca', 'Claire Nicole', 'M', 2],
            ['255316', 'Canino', 'Rowena', 'D', 2],
            ['257036', 'Cerezo', 'Bernadette', 'D', 2],
            ['266368', 'Clapano', 'Marlowe', 'L', 2],
            ['238528', 'Daiz', 'Kristine Kaye', 'T', 2],
            ['252896', 'Dantes', 'Adrian Kobe', 'A', 2],
            ['258227', 'Ditchon', 'Johncel Vic', 'C', 2],
            ['257353', 'Echavez', 'Denice', 'V', 2],
            ['252833', 'Abion', 'Zhaira', 'H', 2],
            ['254821', 'Badilla', 'Charles', 'B', 2],
            ['258008', 'Bancure', 'Rizagine', 'S', 2],
            ['255346', 'Bolaybolay', 'Jenelyn', 'D', 2],
            ['252591', 'Caruana', 'Mark VJ', 'R', 2],
            ['258348', 'Deiparine', 'Rubin', 'B', 2],
            ['254546', 'Francisco', 'Jim Dustin', 'G', 2],
            ['251336', 'Galopo', 'Kelly Grace', 'L', 2],
            ['256762', 'Geno', 'Hanna Claire', 'T', 2],
            ['244148', 'Hashim', 'Mark James', 'T', 2],
            ['258529', 'Horcerada', 'Queziah', 'B', 2],
            ['254505', 'Jalem', 'Kate', 'M', 2],

            // BSCS 1
            ['262421', 'Abrogueña', 'Calix Czar', 'G', 1],
            ['268180', 'Agosto', 'Mark Hanzel', 'G', 1],
            ['263875', 'Aragase', 'Vence Gerard', 'L', 1],
            ['263859', 'Azucena', 'Ralph', 'L', 1],
            ['265306', 'Baño', 'Vincent', null, 1],
            ['267365', 'Bolo', 'Kassandra', 'N', 1],
            ['267969', 'Booc', 'Carl', 'C', 1],
            ['261171', 'Dablo', 'Alliana Jane', 'M', 1],
            ['261431', 'Dabu', 'Dicken', 'O', 1],
            ['266702', 'Daligdig', 'Nash Vincent', 'H', 1],
            ['265451', 'Empeynado', 'Karleen May', 'D', 1],
            ['262844', 'Encarguez', 'Carlyn', 'O', 1],
            ['263478', 'Gabutero', 'David', 'G', 1],
            ['267533', 'Gallogo', 'Shinsi', 'B', 1],
        ];

        InstitutionAccount::query()->delete();

        foreach ($students as [$studId, $lname, $fname, $mname, $year]) {
            InstitutionAccount::create([
                'stud_id' => $studId,
                'password' => '12345678',
                'stud_fname' => $fname,
                'stud_lname' => $lname,
                'stud_mname' => $mname,
                'stud_year' => $year,
                'academic_year' => '2026-2027',
                'semester' => '1st',
                'is_graduated' => false,
                'is_enrolled' => true,
            ]);
        }
    }
}
