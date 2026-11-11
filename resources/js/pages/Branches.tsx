import { BranchCard } from '../components/BranchParts';
import { PageHeader } from '../components/Heading';
import { ParkedCar } from '../components/Studio';
import { pageCars } from '../lib/cars';
import { copy } from '../lib/copy';
import { site } from '../lib/site';
import usePageTitle from '../lib/usePageTitle';

export default function Branches() {
    usePageTitle(copy.branches.title);

    return (
        <>
            <PageHeader
                eyebrow={copy.nav.branches}
                title={copy.branches.title}
                car={<ParkedCar car={pageCars.branches.car} face={pageCars.branches.face} sizes="(min-width: 1024px) 46vw, 100vw" eager contact={pageCars.branches.contact} shadow={pageCars.branches.shadow} />}
            >
                <p>{copy.branches.intro}</p>
            </PageHeader>
            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-8 sm:py-16 xl:px-12">
                <ul className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {site.branches.map((branch) => (
                        <li key={branch.id}>
                            <BranchCard branch={branch} headingLevel="h2" />
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
