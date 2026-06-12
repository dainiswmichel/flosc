<?php
/**
 * FLOSC Sample Data Creator
 * Creates 10 WordPress posts for testing the funnel
 * 
 * STATUS: ✅ FULLY FUNCTIONAL
 * - Creates flosc_sample_data category
 * - Creates 10 posts (1-10)
 * - Adds _flosc_lesson_number meta
 * - Adds _flosc_access_level meta
 * 
 * Run via: wp eval-file admin/create-sample-data.php
 * 
 * @since 9.1.8
 */

if (!defined('ABSPATH')) {
    // Allow running from WP-CLI
    if (defined('WP_CLI') && WP_CLI) {
        // Running from CLI is OK
    } else {
        exit('Direct access not allowed');
    }
}

/**
 * Create flosc_sample_data category if it doesn't exist
 */
function flosc_create_sample_category() {
    $cat = get_category_by_slug('flosc_sample_data');
    
    if (!$cat) {
        $cat_id = wp_create_category('flosc_sample_data');
        WP_CLI::success("Created category 'flosc_sample_data' (ID: {$cat_id})");
        return $cat_id;
    }
    
    WP_CLI::line("Category 'flosc_sample_data' already exists (ID: {$cat->term_id})");
    return $cat->term_id;
}

/**
 * Create the 10 sample posts
 */
function flosc_create_sample_posts() {
    
    $cat_id = flosc_create_sample_category();
    
    $number_words = [
        1 => 'one',
        2 => 'two',
        3 => 'three',
        4 => 'four',
        5 => 'five',
        6 => 'six',
        7 => 'seven',
        8 => 'eight',
        9 => 'nine',
        10 => 'ten'
    ];
    
    // Magnificent titles for each lesson
    $lesson_titles = [
        1 => 'One: The Loneliest Number (But Not Really)',
        2 => 'Two: The Sound That Launched a Thousand Puns',
        3 => 'Three: The Number That Makes You Spit (Literally)',
        4 => 'Four: The Sneaky Diphthong Hiding in Plain Sight',
        5 => 'Five: The Vowel Glide That\'ll Make You Say "Ayyyy"',
        6 => 'Six: The Only Number That Ends With a Hiss',
        7 => 'Seven: The Number With an Identity Crisis',
        8 => 'Eight: The Great Vowel Heist',
        9 => 'Nine: The Diphthong Strikes Back',
        10 => 'Ten: The Final Boss of Counting (It\'s Not That Hard)'
    ];
    
    $created = 0;
    $skipped = 0;
    
    foreach ($number_words as $num => $word) {
        
        // Check if post already exists
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- intentional idempotency check by lesson-number meta pair
        $existing = get_posts([
            'category' => $cat_id,
            'meta_key' => '_flosc_lesson_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- intentional idempotency check by lesson-number meta pair
            'meta_value' => $num, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- intentional idempotency check by lesson-number meta pair
            'posts_per_page' => 1,
            'post_status' => 'any'
        ]);
        
        if (!empty($existing)) {
            WP_CLI::line("Post {$num} already exists, skipping...");
            $skipped++;
            continue;
        }
        
        // Create the post
        $post_data = [
            'post_title' => $lesson_titles[$num],
            'post_content' => flosc_generate_post_content($num, $word),
            'post_status' => 'publish',
            'post_category' => [$cat_id],
            'post_type' => 'post',
            'post_author' => 1 // Admin user
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Add custom meta
            update_post_meta($post_id, '_flosc_lesson_number', $num);
            update_post_meta($post_id, '_flosc_access_level', 'member'); // Default: member-only
            
            WP_CLI::success("Created post {$num}: ID {$post_id}");
            $created++;
        } else {
            $error = is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error';
            WP_CLI::error("Failed to create post {$num}: {$error}");
        }
    }
    
    WP_CLI::success("Sample data creation complete! Created: {$created}, Skipped: {$skipped}");
}

/**
 * Generate post content with <!--more--> tag
 * MAGNIFICENT, ENTERTAINING, JOYFUL lessons with real IPA transcriptions
 */
function flosc_generate_post_content($num, $word) {
    
    // Lesson data: title, teaser, IPA, sound breakdown, fun facts
    $lessons = [
        1 => [
            'title' => 'One: The Loneliest Number (But Not Really)',
            'teaser' => "Ever wonder why \"one\" doesn't rhyme with \"bone\"? Buckle up, language nerds—this seemingly simple word has a pronunciation plot twist that'll make you question everything you thought you knew about English vowels.",
            'ipa' => '/wʌn/',
            'sounds' => [
                '/w/' => 'The "w" sound - Round your lips like you\'re about to whistle, then release into the next sound. Your vocal cords vibrate (it\'s voiced). Try it: "wuh-wuh-wuh"',
                '/ʌ/' => 'The "uh" sound (called "schwa" or "wedge") - This is THE most common vowel in English. Drop your jaw slightly, relax your tongue in the middle of your mouth. It\'s the sound you make when you get punched in the gut: "uh!"',
                '/n/' => 'The "n" sound - Touch your tongue tip to the ridge behind your top teeth. Air flows through your nose (it\'s nasal). Hum it: "nnnnn"'
            ],
            'fun_fact' => 'In Old English, "one" was "ān" (pronounced "ahn"). The "w" sound appeared in Middle English to prevent confusion with "own." English: making things complicated since 1066!'
        ],
        2 => [
            'title' => 'Two: The Sound That Launched a Thousand Puns',
            'teaser' => 'Why does \"two\" sound EXACTLY like \"too\" and \"to\"? Is this a conspiracy? A cosmic joke? Welcome to the wild world of homophones, where three words walk into a bar and sound identical.',
            'ipa' => '/tuː/',
            'sounds' => [
                '/t/' => 'The "t" sound - Press your tongue tip against the ridge behind your top teeth. Build up air pressure, then release explosively. It\'s voiceless (no vocal cord vibration). Pop it: "t-t-t"',
                '/uː/' => 'The "oo" sound (long) - Round your lips tightly and push them forward like you\'re kissing someone across the room. Your tongue pulls back high in your mouth. Hold it: "oooooo"'
            ],
            'fun_fact' => 'The "w" in "two" hasn\'t been pronounced since the 1500s, but we keep writing it because English spelling is where logic goes to die. Fun fact: in Middle English, people DID pronounce it "twoh"!'
        ],
        3 => [
            'title' => 'Three: The Number That Makes You Spit (Literally)',
            'teaser' => 'If you\'re not spraying a little when you say "three," you\'re doing it wrong. This lesson reveals why the TH sound is the ultimate test of English pronunciation mastery—and why so many languages just... don\'t have it.',
            'ipa' => '/θriː/',
            'sounds' => [
                '/θ/' => 'The "th" sound (voiceless) - Stick your tongue between your teeth and blow air out. Do NOT vibrate your vocal cords. It should sound like air escaping a tire: "thhhh." This sound doesn\'t exist in most languages!',
                '/r/' => 'The American "r" sound - Curl your tongue back WITHOUT touching the roof of your mouth. Your tongue tip shouldn\'t touch anything. It\'s the most distinctive sound in American English: "rrrr"',
                '/iː/' => 'The "ee" sound (long) - Spread your lips wide like you\'re smiling. Your tongue is high and forward in your mouth. Stretch it: "eeeeee"'
            ],
            'fun_fact' => 'The θ sound (called "theta") only exists in about 10% of world languages. That\'s why "three" becomes "tree" in many accents. The Ancient Greeks had it—that\'s why their letter is θ (theta)!'
        ],
        4 => [
            'title' => 'Four: The Sneaky Diphthong Hiding in Plain Sight',
            'teaser' => 'You think you\'re saying one vowel, but your mouth is secretly doing TWO. That\'s right, "four" contains a vowel combo move so smooth you probably never noticed it. Prepare to have your mind blown.',
            'ipa' => '/fɔːr/',
            'sounds' => [
                '/f/' => 'The "f" sound - Bite your bottom lip with your top teeth. Blow air through the gap. It\'s voiceless and feels like a gentle breeze: "fffff"',
                '/ɔː/' => 'The "aw" sound (long) - Open your mouth wider than for "oh." Your jaw drops, lips round slightly. This is the sound of realization: "Awwww, I get it now!"',
                '/r/' => 'The American "r" sound (again!) - Remember: curl back, don\'t touch. In American English, we ALWAYS pronounce "r" at the end of words (unlike British "faw")'
            ],
            'fun_fact' => 'British speakers say "faw" (no R sound). Americans say "four" (with the R). This difference—called "rhoticity"—is how you spot an American from 100 yards away. Linguists call Americans "rhotic." Pirates call us "arrrrr-speakers."'
        ],
        5 => [
            'title' => 'Five: The Vowel Glide That\'ll Make You Say "Ayyyy"',
            'teaser' => 'Why does "five" sound like you\'re halfway through saying "eye" before you remember there\'s more word left? Because DIPHTHONGS, baby! Your tongue is about to go on a journey.',
            'ipa' => '/faɪv/',
            'sounds' => [
                '/f/' => 'The "f" sound (you know this one now!) - Top teeth, bottom lip, blow air. Like whispering a secret: "fffff"',
                '/aɪ/' => 'The "eye" diphthong - Start with your jaw dropped and tongue low (like "ah"), then glide up toward "ee." Your mouth moves WHILE making the sound: "ahhh-eee" blended together. This is the sound of surprise!',
                '/v/' => 'The "v" sound - Same mouth position as /f/, but NOW vibrate your vocal cords. It\'s like /f/\'s younger, louder sibling: "vvvvv"'
            ],
            'fun_fact' => 'The "eye" sound /aɪ/ is found in: five, eye, I, my, high, fly, die, pie, and literally any word that makes you sound excited. It\'s called the "Great Vowel Shift diphthong" because English vowels couldn\'t just stay in one place like NORMAL languages.'
        ],
        6 => [
            'title' => 'Six: The Only Number That Ends With a Hiss',
            'teaser' => 'Ssssssssix. Feel that? That\'s the sound of a snake, a leaking tire, and the number after five. Welcome to the world of fricatives—sounds that make you sound perpetually annoyed.',
            'ipa' => '/sɪks/',
            'sounds' => [
                '/s/' => 'The "s" sound - Place your tongue close to the ridge behind your teeth, but DON\'T touch. Force air through the narrow gap. It\'s voiceless and sounds like escaping steam: "sssss"',
                '/ɪ/' => 'The "ih" sound (short) - Relax your tongue in a mid-high position. Your mouth is slightly open. This is NOT "ee"—it\'s shorter, lazier. Say "bit" not "beat." This is the sound of mild disappointment.',
                '/k/' => 'The "k" sound - Press the back of your tongue against the soft part of your roof (velum). Build pressure, release explosively. It\'s voiceless and sounds like you\'re choking (politely): "k-k-k"',
                '/s/' => 'Another "s" sound! - Yes, "six" has TWO hissing sounds. We start with one, end with one. Maximum hiss achieved.'
            ],
            'fun_fact' => '"Six" is spelled with an X, but there\'s NO /z/ sound—it\'s /ks/! X is a LIAR. It\'s actually TWO sounds disguised as one letter. English spelling strikes again!'
        ],
        7 => [
            'title' => 'Seven: The Number With an Identity Crisis',
            'teaser' => 'Is it "SEH-ven" or "SEH-vun"? Trick question—it\'s both, and nobody cares which one you use. Welcome to the beautiful chaos of the schwa sound, where vowels go to retire.',
            'ipa' => '/ˈsɛv.ən/',
            'sounds' => [
                '/s/' => 'The "s" sound (you\'re a pro at this now!) - Tongue near ridge, air through gap, sound like a hiss',
                '/ɛ/' => 'The "eh" sound - Open your mouth slightly more than for /ɪ/. Your tongue is mid-height, forward. This is the sound of "meh" and mild confusion. Say "bed" not "bid."',
                '/v/' => 'The "v" sound (returning champion!) - Top teeth on bottom lip, vibrate vocal cords',
                '/ə/' => 'The schwa (uh) sound - The LAZIEST vowel. Your mouth barely moves. Every unstressed syllable eventually becomes schwa. It\'s the most common sound in English because we\'re all too tired to pronounce things properly.',
                '/n/' => 'The "n" sound (again!) - Tongue tip to ridge, air through nose'
            ],
            'fun_fact' => 'Most people actually say "SEV-un" not "SEV-en." The second syllable gets so unstressed it turns into schwa /ə/. Linguists call this "vowel reduction." Normal people call it "talking like a human."'
        ],
        8 => [
            'title' => 'Eight: The Great Vowel Heist',
            'teaser' => 'Once upon a time, "eight" had a totally different vowel sound. Then the Great Vowel Shift happened (1400-1700), English went through puberty, and now we\'re stuck with this glorious diphthong disaster.',
            'ipa' => '/eɪt/',
            'sounds' => [
                '/eɪ/' => 'The "ay" diphthong - Start with your mouth half-open, tongue mid-height (like "eh"), then glide upward toward "ee." It\'s smooth, sophisticated, the sound of agreement: "Aaaayyyy!" Also found in: day, say, may, bae.',
                '/t/' => 'The "t" sound (final position) - In American English, final /t/ can be: (1) fully released with a puff of air, (2) unreleased (tongue stops but doesn\'t pop), or (3) glottalized (throat stops the air). Say "eight" three times and notice what YOUR mouth does!'
            ],
            'fun_fact' => 'In Middle English (Chaucer times), "eight" was pronounced more like "AKH-tuh." The Great Vowel Shift changed EVERYTHING. If you time-traveled to 1400, you couldn\'t understand English. You\'d need subtitles in your own language!'
        ],
        9 => [
            'title' => 'Nine: The Diphthong Strikes Back',
            'teaser' => 'Thought you understood diphthongs after "five"? THINK AGAIN. "Nine" takes that /aɪ/ sound and adds a nasal finale that\'ll make your nose vibrate like a tuning fork.',
            'ipa' => '/naɪn/',
            'sounds' => [
                '/n/' => 'The "n" sound (opening position) - Tongue tip to ridge, air through nose. When /n/ starts a word, it\'s more forceful than when it ends one.',
                '/aɪ/' => 'The "eye" diphthong (AGAIN!) - Jaw drops (ah) then glides up (ee). Same as in "five." You\'re basically saying "nah-een" really fast.',
                '/n/' => 'The "n" sound (final position) - Ends with tongue touching ridge, holding it there while air flows through your nose. Hold the "nnnnn" and feel the nasal vibration!'
            ],
            'fun_fact' => '"Nine" is one of the few English numbers that rhymes with tons of words: fine, mine, line, wine, pine, shine, divine, feline, alkaline... The /aɪn/ ending is SUPER productive in English. Meanwhile "orange" is over there rhyming with nothing like a loser.'
        ],
        10 => [
            'title' => 'Ten: The Final Boss of Counting (It\'s Not That Hard)',
            'teaser' => 'Congratulations! You made it to double digits. "Ten" is refreshingly simple after all those diphthongs and schwas. Just three sounds, boom, you\'re done. You\'ve earned this.',
            'ipa' => '/tɛn/',
            'sounds' => [
                '/t/' => 'The "t" sound (you\'re basically a /t/ expert now) - Tongue tip to ridge, build pressure, explosive release, voiceless',
                '/ɛ/' => 'The "eh" sound (from "seven"!) - Mid-open mouth, tongue mid-height forward. The sound of "meh" but also "ten." Say "pen" not "pin."',
                '/n/' => 'The "n" sound (final bow) - Tongue to ridge, nasal airflow, hold it with pride. You made it to TEN!'
            ],
            'fun_fact' => 'We have a base-10 number system (decimal) because humans have 10 fingers. If we had 8 fingers, we\'d count in base-8 (octal). Computers count in base-2 (binary) because they only have two fingers: 0 and 1. Life is weird.'
        ]
    ];
    
    $lesson = $lessons[$num];
    
    // Build the content
    $content = "# {$lesson['title']}\n\n";
    $content .= "{$lesson['teaser']}\n\n";
    $content .= "**Spoiler alert:** There's actual linguistic science below. Members get the full IPA breakdown, pronunciation secrets, and fun facts that'll make you the hit of parties (nerdy parties, but still).\n\n";
    $content .= "<!--more-->\n\n";
    $content .= "## 🎯 Full IPA Transcription\n\n";
    $content .= "**\"{$word}\" = {$lesson['ipa']}**\n\n";
    $content .= "---\n\n";
    $content .= "## 🔊 Sound-by-Sound Breakdown\n\n";
    
    foreach ($lesson['sounds'] as $ipa => $description) {
        $content .= "### {$ipa}\n\n";
        $content .= "{$description}\n\n";
    }
    
    $content .= "---\n\n";
    $content .= "## 🎓 Linguistic Fun Fact\n\n";
    $content .= "{$lesson['fun_fact']}\n\n";
    $content .= "---\n\n";
    $content .= "## ✍️ Practice Exercise\n\n";
    $content .= "1. **Record yourself** saying \"{$word}\" 10 times slowly\n";
    $content .= "2. **Focus on each sound** - Don't rush through it\n";
    $content .= "3. **Compare to a native speaker** - YouTube is your friend\n";
    $content .= "4. **Exaggerate each sound** - Make it theatrical, then dial it back\n\n";
    $content .= "Remember: Pronunciation isn't about perfection—it's about being understood. You've got this! 🎉\n\n";
    $content .= "---\n\n";
    $content .= "*P.S. - Yes, English pronunciation is chaotic. Yes, it's inconsistent. Yes, we keep silent letters for no reason. Welcome to the beautiful disaster that is the English language. We're all just doing our best here.* 😅\n";
    
    return $content;
}

// Run if called from WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    flosc_create_sample_posts();
}

// Provide admin UI button (future enhancement)
function flosc_sample_data_admin_ui() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_POST['flosc_create_sample_data']) && check_admin_referer('flosc_sample_data')) {
        flosc_create_sample_posts();
    }
    
    ?>
    <div class="wrap">
        <h1>FLOSC Sample Data</h1>
        <p>Create 10 sample WordPress posts for testing the sales funnel.</p>
        <form method="post">
            <?php wp_nonce_field('flosc_sample_data'); ?>
            <p>
                <button type="submit" name="flosc_create_sample_data" class="button button-primary">
                    Create Sample Posts (1-10)
                </button>
            </p>
        </form>
    </div>
    <?php
}
