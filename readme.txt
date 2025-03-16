=== Mind - AI Page Builder ===
Contributors:      nko
Tags:              ai, gpt, ai page builder, ai editor, copilot
Requires at least: 6.2
Tested up to:      6.7
Requires PHP:      7.2
Stable tag:        0.2.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

AI-powered page builder for WordPress that creates complete sections, redesigns existing blocks, and builds entire pages with natural language prompts.

== Description ==

Mind is a WordPress plugin that transforms your page building experience. Powered by AI technology, it helps you create and modify entire page sections, layouts, and content directly in the WordPress editor. With support for both Anthropic and OpenAI AI models, Mind seamlessly integrates with the WordPress block editor to enhance your page building workflow.

=== 🏗️ Complete Page Building Solution ===

Mind is not just an AI writing assistant - it's a full-featured page builder that allows you to:

- Create entire page layouts with a simple text prompt
- Design custom sections with specific styles and content
- Modify and improve existing page sections
- Build complex page structures without coding knowledge

=== 🚀 Community-Driven Development ===

This plugin was created as an experiment in the hope that the community will influence the course of its development. Any wishes, feature requests, or bug reports are welcome and will be the motivation for developing the functionality of the plugin in [GitHub Discussions](https://github.com/nk-crew/mind/discussions).

=== ✍️ Writing Assistance ===

Mind provides various tools to help with writing content. You can use it to write an entire post, create a catchy post title, or draft a comprehensive post outline. Whether you need assistance in brainstorming ideas or structuring your content, Mind is there to support you.

- Write a post about specific topic
- Write a post title about specific topic
- Write a post outline about specific topic

=== 📝 Writing Language Improvement ===

With Mind, you can improve the language of your writing. It helps you fix spelling and grammar errors, ensuring that your content is polished and professional. Additionally, Mind can make your writing shorter or longer, based on your preferences.

- Improve writing language
- Fix spelling & grammar
- Make shorter
- Make longer

=== ✂️ Summarization and Paraphrasing ===

Mind offers the ability to summarize lengthy content, making it easier for readers to grasp the main points. It can also help you paraphrase sentences or paragraphs, ensuring that your content is unique and engaging.

- Summarize
- Paraphrase

=== 🤗 Tone Adjustment ===

Mind allows you to adjust the tone of your writing to suit your desired style.

- Professional
- Friendly
- Straightforward
- Educational
- Confident
- Witty
- Heartfelt

=== 🌐 Translation ===

Mind supports translation into multiple languages. It enables you to reach a wider audience by translating your content accurately and efficiently. Supported languages:

- Chinese
- Dutch
- English
- Filipino
- French
- German
- Indonesian
- Italian
- Japanese
- Korean
- Portuguese
- Russian
- Spanish

=== ⚙️ Features ===

There are multiple ways to use Mind in your WordPress site:

- **Mind Popup** - Open the popup to talk with AI to write blog post content, create page sections, etc...
- **Page Section Builder** - Generate complete page sections with custom layouts and content
- **Block Enhancement** - Select existing blocks and enhance them using the Toolbar Mind button
- **Quick Access** - Press `space` in an empty paragraph to instantly open the Mind Popup and make a request
- **Whole Page Creation** - Generate entire pages based on your requirements with a single prompt

=== Other ===

With its comprehensive set of features, Mind empowers content creators to build high-quality pages and engaging posts, while saving time and effort in the content creation process.

p.s. this plugin description is created using Mind and OpenAI API.

=== OpenAI and Anthropic ===

The Mind plugin utilizes both [OpenAI](https://platform.openai.com/) and [Anthropic](https://www.anthropic.com/) APIs without collecting any personal information. Data transmitted to these AI providers' servers includes post content and specified context needed to generate responses.

For the Mind plugin to function correctly, you need an API key from either OpenAI or Anthropic:
- Sign up at <https://platform.openai.com/account/api-keys> to obtain an OpenAI API key
- Sign up at <https://console.anthropic.com/> to obtain an Anthropic API key

Both services have their own data handling policies:

**OpenAI Data Usage:**
- Data sent to OpenAI may be used to improve their models
- You can opt out of having your data used for training in your OpenAI account settings
- Please review their [Privacy Policy](https://openai.com/policies/privacy-policy) and [Terms of Use](https://openai.com/policies/terms-of-use) for more information

**Anthropic Data Usage:**
- Anthropic has similar data retention policies for service improvement
- They offer data handling options for enterprise customers
- Please review their [Privacy Policy](https://www.anthropic.com/privacy) and [Terms of Service](https://www.anthropic.com/terms) for more information

Your choice of AI provider can be configured in the plugin settings.

== Installation ==

= Automatic installation =

Install the Mind either via the WordPress plugin directory or by uploading the files to your server at `wp-content/plugins`.

= Usage =

To start using the Mind features you have to insert your OpenAI API key in the plugin settings.

- Create OpenAI API key <https://platform.openai.com/account/api-keys>
- Insert this key in the plugin settings (Admin Menu → Mind → Settings)
- Open any post or page in WordPress editor (Gutenberg) and you can see the new button in the editor and paragraph toolbars

== Frequently Asked Questions ==

= Website and Documentation =

There is no documentation available yet, but in the future it will be placed here - <https://www.wp-mind.com/>

= Supported page builders =

Mind is developed for the WordPress page builder - Gutenberg. Currently we don't have support for 3rd-party builders.

= Can I create entire page layouts with Mind? =

Yes! Mind can now generate complete page layouts and sections based on your text prompts. Simply describe what you want, and the AI will create responsive sections that fit your needs.

= How do I improve existing page sections? =

Select the section you want to enhance, click the Mind button in the toolbar, and describe how you'd like to improve it. Mind will intelligently modify the selected section while maintaining its structure.

== Screenshots ==

1. Example of block section enhancement with simple request
2. Ask AI to enhance section with blocks
3. Result of enhanced section
4. Paragraph toolbar button

== Changelog ==

= 0.2.0 - Dec 9, 2024 =

- added stream AI response for better experience
- fixed JS error when inserting response to editor
- changed model to gpt-4o-mini

= 0.1.3 - Nov 28, 2024 =

- fixed the Open Mind button display issue in the WordPress 6.7 posts toolbar

= 0.1.2 - Nov 28, 2024 =

- added translation files
- check compatibility with WordPress 6.7

= 0.1.1 - 21 Jan, 2024 =

- fixed displaying OpenAI errors

= 0.1.0 - 14 Nov, 2023 =

- Release
