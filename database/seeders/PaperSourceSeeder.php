<?php

namespace Database\Seeders;

use App\Enums\PaperSourceType;
use App\Models\Board;
use Illuminate\Database\Seeder;

/**
 * Local-only convenience data — only runs when APP_ENV=local, so it never
 * touches a staging/production database even if called directly. The
 * education boards and the best-known medical and university admission
 * tests, each named in Bangla and English, full and short. On a live site
 * the Admin adds sources in "Paper Sources".
 *
 * Safe to run again: a source is matched by its English name and only its
 * other names and kind are filled in.
 */
class PaperSourceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        foreach ($this->sources() as [$type, $nameBn, $name, $shortBn, $short]) {
            Board::updateOrCreate(
                ['name' => $name],
                ['name_bn' => $nameBn, 'short_name_bn' => $shortBn, 'short_name' => $short, 'type' => $type],
            );
        }
    }

    /**
     * @return array<int, array{0: PaperSourceType, 1: string, 2: string, 3: string, 4: string}>
     */
    private function sources(): array
    {
        $board = PaperSourceType::Board;
        $medical = PaperSourceType::Medical;
        $university = PaperSourceType::University;

        return [
            [$board, 'ঢাকা বোর্ড', 'Dhaka Board', 'ঢাকা', 'DHA'],
            [$board, 'রাজশাহী বোর্ড', 'Rajshahi Board', 'রাজশাহী', 'RAJ'],
            [$board, 'কুমিল্লা বোর্ড', 'Cumilla Board', 'কুমিল্লা', 'CUM'],
            [$board, 'যশোর বোর্ড', 'Jashore Board', 'যশোর', 'JAS'],
            [$board, 'চট্টগ্রাম বোর্ড', 'Chattogram Board', 'চট্টগ্রাম', 'CTG'],
            [$board, 'বরিশাল বোর্ড', 'Barishal Board', 'বরিশাল', 'BAR'],
            [$board, 'সিলেট বোর্ড', 'Sylhet Board', 'সিলেট', 'SYL'],
            [$board, 'দিনাজপুর বোর্ড', 'Dinajpur Board', 'দিনাজপুর', 'DIN'],
            [$board, 'ময়মনসিংহ বোর্ড', 'Mymensingh Board', 'ময়মনসিংহ', 'MYM'],
            [$medical, 'মেডিকেল (এমবিবিএস) ভর্তি পরীক্ষা', 'Medical (MBBS) Admission', 'মেডিকেল', 'MBBS'],
            [$medical, 'ডেন্টাল (বিডিএস) ভর্তি পরীক্ষা', 'Dental (BDS) Admission', 'ডেন্টাল', 'BDS'],
            [$university, 'ঢাকা বিশ্ববিদ্যালয়', 'University of Dhaka', 'ঢাবি', 'DU'],
            [$university, 'জাহাঙ্গীরনগর বিশ্ববিদ্যালয়', 'Jahangirnagar University', 'জাবি', 'JU'],
            [$university, 'রাজশাহী বিশ্ববিদ্যালয়', 'University of Rajshahi', 'রাবি', 'RU'],
            [$university, 'চট্টগ্রাম বিশ্ববিদ্যালয়', 'University of Chittagong', 'চবি', 'CU'],
            [$university, 'জগন্নাথ বিশ্ববিদ্যালয়', 'Jagannath University', 'জবি', 'JnU'],
            [$university, 'বাংলাদেশ প্রকৌশল বিশ্ববিদ্যালয়', 'Bangladesh University of Engineering and Technology', 'বুয়েট', 'BUET'],
            [$university, 'শাহজালাল বিজ্ঞান ও প্রযুক্তি বিশ্ববিদ্যালয়', 'Shahjalal University of Science and Technology', 'শাবিপ্রবি', 'SUST'],
        ];
    }
}
