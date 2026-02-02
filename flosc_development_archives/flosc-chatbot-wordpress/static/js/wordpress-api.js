/**
 * WordPress REST API Integration for FLOSC
 * Connects to dainis.net/lesaep/ category
 */

class WordPressAPI {
    constructor(siteUrl, categorySlug) {
        this.baseUrl = `${siteUrl}/wp-json/wp/v2`;
        this.categorySlug = categorySlug;
        this.categoryId = null;
    }

    /**
     * Initialize - Get category ID from slug
     */
    async init() {
        try {
            const response = await fetch(`${this.baseUrl}/categories?slug=${this.categorySlug}`);
            const categories = await response.json();
            
            if (categories.length > 0) {
                this.categoryId = categories[0].id;
                console.log(`✅ Connected to WordPress category: ${this.categorySlug} (ID: ${this.categoryId})`);
                return true;
            } else {
                console.warn(`⚠️ Category "${this.categorySlug}" not found. Will create mock data.`);
                return false;
            }
        } catch (error) {
            console.error('❌ WordPress connection failed:', error);
            return false;
        }
    }

    /**
     * Get all lessons in category
     */
    async getLessons(freeOnly = false) {
        if (!this.categoryId) {
            return this.getMockLessons(freeOnly);
        }

        try {
            let url = `${this.baseUrl}/posts?categories=${this.categoryId}&per_page=100&_embed`;
            
            // If we want only free lessons, add meta query
            if (freeOnly) {
                url += `&meta_key=_flosc_is_free&meta_value=1`;
            }
            
            const response = await fetch(url);
            const posts = await response.json();
            
            return posts.map(post => ({
                id: post.id,
                title: post.title.rendered,
                excerpt: post.excerpt.rendered,
                content: post.content.rendered,
                isFree: post.meta?._flosc_is_free === '1',
                phonemeGroup: post.meta?._flosc_phoneme_group || null,
                permalink: post.link,
                thumbnail: post._embedded?.['wp:featuredmedia']?.[0]?.source_url || null
            }));
        } catch (error) {
            console.error('Error fetching lessons:', error);
            return this.getMockLessons(freeOnly);
        }
    }

    /**
     * Get lessons by phoneme groups
     */
    async getLessonsByPhonemes(phonemes, freeOnly = false) {
        const allLessons = await this.getLessons(freeOnly);
        
        return allLessons.filter(lesson => {
            if (!lesson.phonemeGroup) return false;
            return phonemes.includes(lesson.phonemeGroup);
        });
    }

    /**
     * Get single lesson by ID
     */
    async getLesson(lessonId) {
        if (!this.categoryId) {
            const mockLessons = this.getMockLessons();
            return mockLessons.find(l => l.id === lessonId) || null;
        }

        try {
            const response = await fetch(`${this.baseUrl}/posts/${lessonId}`);
            const post = await response.json();
            
            return {
                id: post.id,
                title: post.title.rendered,
                content: post.content.rendered,
                isFree: post.meta?._flosc_is_free === '1',
                phonemeGroup: post.meta?._flosc_phoneme_group || null
            };
        } catch (error) {
            console.error('Error fetching lesson:', error);
            return null;
        }
    }

    /**
     * Search lessons
     */
    async searchLessons(query) {
        if (!this.categoryId) {
            return this.getMockLessons().filter(l => 
                l.title.toLowerCase().includes(query.toLowerCase())
            );
        }

        try {
            const response = await fetch(
                `${this.baseUrl}/posts?categories=${this.categoryId}&search=${encodeURIComponent(query)}&_embed`
            );
            const posts = await response.json();
            
            return posts.map(post => ({
                id: post.id,
                title: post.title.rendered,
                excerpt: post.excerpt.rendered,
                isFree: post.meta?._flosc_is_free === '1',
                phonemeGroup: post.meta?._flosc_phoneme_group || null,
                permalink: post.link
            }));
        } catch (error) {
            console.error('Error searching lessons:', error);
            return [];
        }
    }

    /**
     * Mock lessons (for when WordPress isn't ready)
     */
    getMockLessons(freeOnly = false) {
        const lessons = [
            {
                id: 1,
                title: "Mastering the Short A Sound (/æ/)",
                excerpt: "<p>Learn to pronounce words like 'cat', 'bat', and 'mat' with confidence.</p>",
                content: `
                    <h2>Introduction</h2>
                    <p>The /æ/ sound is one of the most common challenges for non-native English speakers.</p>
                    
                    <h2>How to Produce the Sound</h2>
                    <ol>
                        <li>Open your mouth wider than you think</li>
                        <li>Keep your tongue low and flat</li>
                        <li>Let the sound come from your throat</li>
                    </ol>
                    
                    <h2>Practice Words</h2>
                    <ul>
                        <li>cat, bat, hat, mat</li>
                        <li>bad, dad, sad, mad</li>
                        <li>back, pack, sack, rack</li>
                    </ul>
                    
                    <h2>Common Mistakes</h2>
                    <p>Many learners make the sound too short or too closed. Remember: WIDE mouth!</p>
                `,
                isFree: true,
                phonemeGroup: '/æ/',
                permalink: 'https://dainis.net/lesaep/lesson-short-a-sound'
            },
            {
                id: 2,
                title: "The Voiced TH Sound (/ð/)",
                excerpt: "<p>Master 'this', 'that', 'these', and 'those' with proper tongue placement.</p>",
                content: `
                    <h2>Introduction</h2>
                    <p>The /ð/ sound requires your tongue between your teeth - it feels strange at first!</p>
                    
                    <h2>How to Produce</h2>
                    <ol>
                        <li>Place tongue tip between teeth</li>
                        <li>Turn on your voice (vibration in throat)</li>
                        <li>Push air through</li>
                    </ol>
                    
                    <h2>Practice Words</h2>
                    <ul>
                        <li>this, that, these, those</li>
                        <li>the, they, them, then</li>
                        <li>mother, father, brother</li>
                    </ul>
                `,
                isFree: true,
                phonemeGroup: '/ð/',
                permalink: 'https://dainis.net/lesaep/lesson-voiced-th'
            },
            {
                id: 3,
                title: "The R Sound - Complete Mastery",
                excerpt: "<p>The infamous English R sound - we'll break it down step by step.</p>",
                content: `
                    <h2>Introduction</h2>
                    <p>The /r/ sound is difficult because it doesn't exist in many languages.</p>
                    
                    <h2>Two Ways to Make R</h2>
                    <h3>Method 1: Bunched</h3>
                    <p>Pull your tongue back and bunch it up.</p>
                    
                    <h3>Method 2: Retroflexed</h3>
                    <p>Curl the tip of your tongue back.</p>
                    
                    <h2>Practice Positions</h2>
                    <ul>
                        <li>Initial R: red, run, right</li>
                        <li>Medial R: very, merry, sorry</li>
                        <li>Final R: car, far, star</li>
                    </ul>
                `,
                isFree: false,
                phonemeGroup: '/r/',
                permalink: 'https://dainis.net/lesaep/lesson-r-sound'
            },
            {
                id: 4,
                title: "Vowel Sounds - Complete Guide",
                excerpt: "<p>All 15 English vowel sounds explained with practice exercises.</p>",
                content: "<h2>Complete vowel mastery course</h2><p>[Full content here]</p>",
                isFree: false,
                phonemeGroup: 'vowels',
                permalink: 'https://dainis.net/lesaep/lesson-vowels'
            },
            {
                id: 5,
                title: "Connected Speech Training",
                excerpt: "<p>Learn how native speakers connect words naturally in conversation.</p>",
                content: "<h2>Connected speech patterns</h2><p>[Full content here]</p>",
                isFree: false,
                phonemeGroup: 'connected-speech',
                permalink: 'https://dainis.net/lesaep/lesson-connected-speech'
            }
        ];

        return freeOnly ? lessons.filter(l => l.isFree) : lessons;
    }

    /**
     * Get lesson count
     */
    async getLessonCount(freeOnly = false) {
        const lessons = await this.getLessons(freeOnly);
        return lessons.length;
    }
}

// Export for use
window.WordPressAPI = WordPressAPI;
